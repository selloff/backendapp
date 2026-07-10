<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Notification\Mail\TransactionalMail;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\DelayedMessageEmailGuard;
use App\Modules\Selloff\Notification\Support\TransactionalMailBranding;
use DateTimeInterface;
use Illuminate\Support\Arr;

class TransactionalEmailService
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly EmailOptionGate $gate,
        private readonly PlatformMailService $mail,
        private readonly TransactionalMailBranding $branding,
        private readonly DelayedMessageEmailGuard $messageGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function queue(
        string $type,
        string $to,
        array $data = [],
        ?string $subject = null,
        ?string $template = null,
        ?DateTimeInterface $scheduledAt = null,
        array $metadata = [],
    ): ?EmailJob {
        if ($to === '' || ! $this->gate->isEnabled($type)) {
            return null;
        }

        $resolvedSubject = $subject ?? (string) Arr::get($data, 'subject', 'Notification from Selloff');
        $resolvedTemplate = $template ?? (string) Arr::get($data, 'template', 'main');

        return EmailJob::query()->create([
            'to_email' => $to,
            'email_type' => $type,
            'subject' => $resolvedSubject,
            'body' => (string) Arr::get($data, 'body', ''),
            'template' => $resolvedTemplate,
            'template_data' => $data,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
            'metadata' => array_merge(['type' => $type], $metadata),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendNow(
        string $type,
        string $to,
        array $data = [],
        ?string $subject = null,
        ?string $template = null,
    ): void {
        if ($to === '' || ! $this->gate->isEnabled($type)) {
            return;
        }

        $job = new EmailJob([
            'to_email' => $to,
            'email_type' => $type,
            'subject' => $subject ?? (string) Arr::get($data, 'subject', 'Notification from Selloff'),
            'body' => (string) Arr::get($data, 'body', ''),
            'template' => $template ?? (string) Arr::get($data, 'template', 'main'),
            'template_data' => $data,
            'status' => 'pending',
            'metadata' => ['type' => $type],
        ]);

        $this->dispatch($job);
        $job->forceFill([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function process(EmailJob $job): void
    {
        $type = (string) ($job->email_type ?? $job->metadata['type'] ?? 'unknown');

        if (! $this->gate->isEnabled($type)) {
            $job->update([
                'status' => 'skipped',
                'skipped_at' => now(),
            ]);

            return;
        }

        if ($job->scheduled_at !== null && $job->scheduled_at->isFuture()) {
            return;
        }

        $skipReason = $this->messageGuard->shouldSkipDelivery($job);
        if ($skipReason !== null) {
            $job->update([
                'status' => 'skipped',
                'skipped_at' => now(),
                'last_error' => $skipReason,
            ]);

            return;
        }

        try {
            $this->dispatch($job);

            $job->update([
                'status' => 'sent',
                'sent_at' => now(),
                'attempts' => $job->attempts + 1,
                'last_error' => null,
            ]);
        } catch (\Throwable $exception) {
            $attempts = $job->attempts + 1;

            $job->update([
                'status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending',
                'attempts' => $attempts,
                'last_error' => $exception->getMessage(),
            ]);
        }
    }

    private function dispatch(EmailJob $job): void
    {
        if ($job->template) {
            $this->mail->sendMailable(
                new TransactionalMail(
                    mailSubject: (string) $job->subject,
                    template: (string) $job->template,
                    templateData: $job->template_data ?? [],
                    branding: $this->branding->resolve(),
                ),
                (string) $job->to_email,
            );

            return;
        }

        $body = trim((string) $job->body);
        if ($body === '') {
            throw new \RuntimeException('Email job has no template or body content.');
        }

        $this->mail->sendRaw(
            (string) $job->to_email,
            (string) $job->subject,
            $body,
            html: str_contains($body, '<'),
        );
    }
}
