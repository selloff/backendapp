<?php

namespace App\Modules\Selloff\Notification\Console;

use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Services\TransactionalEmailService;
use Illuminate\Console\Command;

class ProcessEmailJobsCommand extends Command
{
    protected $signature = 'selloff:send-email-jobs {--limit=25 : Maximum jobs to process per run}';

    protected $description = 'Process pending transactional email jobs from the email_jobs queue';

    public function handle(TransactionalEmailService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $jobs = EmailJob::query()
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderByRaw("COALESCE(scheduled_at, '1970-01-01 00:00:00')")
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $previousStatus = $job->status;
            $service->process($job->fresh());
            $job->refresh();
            $processed++;

            if ($job->status === 'sent') {
                $sent++;
            } elseif ($job->status === 'skipped') {
                $skipped++;
            } elseif ($job->status === 'failed' && $previousStatus === 'pending') {
                $failed++;
            }
        }

        $this->info("Processed {$processed} email job(s): {$sent} sent, {$skipped} skipped, {$failed} failed.");

        return self::SUCCESS;
    }
}
