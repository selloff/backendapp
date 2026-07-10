<?php

namespace App\Modules\Selloff\Support\Services;

use App\Models\User;
use App\Modules\Selloff\Notification\Services\PlatformMailService;
use App\Modules\Selloff\Support\Models\ContactMessage;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

class ContactMessageNotificationService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly PlatformMailService $mail,
    ) {}

    public function buildReplySubject(ContactMessage $contactMessage): string
    {
        $original = trim((string) ($contactMessage->subject ?: 'Contact message'));

        if (preg_match('/^re:\s/i', $original) === 1) {
            return $original;
        }

        return "Re: {$original}";
    }

    /**
     * @return array{address: string, name: string}
     */
    public function resolveReplyFrom(): array
    {
        $platform = $this->settings->all();

        $address = trim((string) ($platform['contact_email'] ?? ''));
        if ($address === '') {
            $address = trim((string) config('selloff.platform_settings.contact_email', ''));
        }
        if ($address === '') {
            $address = trim((string) ($platform['mail_from_address'] ?? config('mail.from.address', '')));
        }

        $name = trim((string) ($platform['mail_from_name'] ?? config('mail.from.name', '')));
        if ($name === '') {
            $name = trim((string) ($platform['site_name'] ?? config('app.name', 'Selloff')));
        }

        return [
            'address' => $address,
            'name' => $name,
        ];
    }

    public function sendAdminReply(ContactMessage $contactMessage, string $replyBody, User $admin): void
    {
        $to = trim((string) $contactMessage->email);
        if ($to === '') {
            throw new InvalidArgumentException('Contact message has no sender email address.');
        }

        $platform = $this->settings->all();
        $siteName = trim((string) ($platform['site_name'] ?? config('app.name')));
        $subject = $this->buildReplySubject($contactMessage);
        $from = $this->resolveReplyFrom();

        $name = trim((string) ($contactMessage->name ?: 'there'));
        $adminName = trim($admin->name ?: $admin->email);

        $body = "Hi {$name},\n\n"
            .trim($replyBody)."\n\n"
            ."— {$adminName}\n"
            .($siteName !== '' ? "{$siteName}\n\n" : "\n")
            ."---\n"
            ."Your original message:\n"
            .trim((string) $contactMessage->message);

        try {
            $this->mail->configureTransport();

            Mail::raw($body, function ($message) use ($to, $subject, $from): void {
                $message->to($to)->subject($subject);

                if ($from['address'] !== '') {
                    $message->from($from['address'], $from['name'] !== '' ? $from['name'] : null);
                    $message->replyTo($from['address'], $from['name'] !== '' ? $from['name'] : null);
                }
            });
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to send contact reply email.', 0, $exception);
        }
    }
}
