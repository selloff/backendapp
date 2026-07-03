<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Payment\Services\MembershipExpiryNotificationService;
use Illuminate\Console\Command;

class NotifyExpiredMembershipsCommand extends Command
{
    protected $signature = 'selloff:notify-expired-memberships {--days=2 : Look back this many days for newly expired memberships}';

    protected $description = 'Email vendors whose membership plans have expired and include a renew link';

    public function handle(MembershipExpiryNotificationService $service): int
    {
        $lookbackDays = max(1, (int) $this->option('days'));
        $sent = $service->notifyDueSubscriptions($lookbackDays);

        $this->info("Sent {$sent} membership expiry notification(s).");

        return self::SUCCESS;
    }
}
