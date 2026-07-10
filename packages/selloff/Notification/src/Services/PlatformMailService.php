<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Notification\Mail\TestMail;
use App\Modules\Selloff\Notification\Support\TransactionalMailBranding;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;

class PlatformMailService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly MailtrapMailConfigurator $mailtrap,
    ) {}

    public function sendRaw(string $to, string $subject, string $body, bool $html = false): void
    {
        if ($to === '') {
            return;
        }

        $this->configureTransport();

        if ($html) {
            Mail::html($body, function ($message) use ($to, $subject): void {
                $this->applyEnvelope($message, $to, $subject);
            });

            return;
        }

        Mail::raw($body, function ($message) use ($to, $subject): void {
            $this->applyEnvelope($message, $to, $subject);
        });
    }

    public function sendMailable(Mailable $mailable, string $to): void
    {
        if ($to === '') {
            return;
        }

        $this->configureTransport();
        Mail::to($to)->send($mailable);
    }

    public function sendTestEmail(string $to): void
    {
        $branding = app(TransactionalMailBranding::class)->resolve();

        $this->sendMailable(
            new TestMail(
                mailSubject: 'Selloff test email',
                branding: $branding,
            ),
            $to,
        );
    }

    public function configureTransport(): void
    {
        if (Mail::getFacadeRoot() instanceof MailFake) {
            return;
        }

        if (app()->environment('testing')) {
            return;
        }

        $settings = $this->settings->all();
        $service = (string) ($settings['mail_service']
            ?? config('selloff.mail.default_service', 'mailtrap'));

        Config::set('mail.default', 'smtp');
        Config::set('mail.from', $this->resolveFrom($settings));

        match ($service) {
            'mailtrap' => $this->configureMailtrap($settings),
            'brevo' => $this->configureBrevo($settings),
            'mailgun' => $this->configureMailgun($settings),
            default => $this->configureSmtp($settings),
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{address: string, name: string}
     */
    public function resolveFrom(?array $settings = null): array
    {
        $settings ??= $this->settings->all();

        return [
            'address' => (string) ($settings['mail_from_address']
                ?? config('mail.from.address')
                ?? 'noreply@selloff.test'),
            'name' => (string) ($settings['mail_from_name']
                ?? config('mail.from.name')
                ?? 'Selloff'),
        ];
    }

    public function resolveReplyTo(): ?string
    {
        $settings = $this->settings->all();
        $replyTo = (string) ($settings['mail_reply_to'] ?? '');

        return $replyTo !== '' ? $replyTo : null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function configureMailtrap(array $settings): void
    {
        $smtp = $this->mailtrap->smtpConfig($settings);

        Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
            'transport' => 'smtp',
            'host' => $smtp['host'],
            'port' => $smtp['port'],
            'username' => $smtp['username'],
            'password' => $smtp['password'],
            'encryption' => $smtp['encryption'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function configureSmtp(array $settings): void
    {
        $encryption = (string) ($settings['mail_encryption'] ?? 'tls');

        Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
            'transport' => 'smtp',
            'host' => (string) ($settings['smtp_host'] ?? config('mail.mailers.smtp.host', '127.0.0.1')),
            'port' => (int) ($settings['smtp_port'] ?? config('mail.mailers.smtp.port', 587)),
            'username' => $settings['smtp_username'] ?? config('mail.mailers.smtp.username'),
            'password' => $settings['smtp_password'] ?? config('mail.mailers.smtp.password'),
            'encryption' => $encryption === 'ssl' ? 'ssl' : ($encryption === 'tls' ? 'tls' : null),
        ]));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function configureBrevo(array $settings): void
    {
        $apiKey = (string) ($settings['brevo_api_key'] ?? '');

        Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
            'transport' => 'smtp',
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'username' => $apiKey,
            'password' => $apiKey,
            'encryption' => 'tls',
        ]));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function configureMailgun(array $settings): void
    {
        $domain = (string) ($settings['mailgun_domain'] ?? '');
        $apiKey = (string) ($settings['mailgun_api_key'] ?? '');
        $region = (string) ($settings['mailgun_region'] ?? 'us');
        $host = $region === 'eu' ? 'smtp.eu.mailgun.org' : 'smtp.mailgun.org';

        Config::set('mail.mailers.smtp', array_merge(config('mail.mailers.smtp', []), [
            'transport' => 'smtp',
            'host' => $host,
            'port' => 587,
            'username' => $domain !== '' ? "postmaster@{$domain}" : null,
            'password' => $apiKey !== '' ? $apiKey : null,
            'encryption' => 'tls',
        ]));
    }

    private function applyEnvelope($message, string $to, string $subject): void
    {
        $from = $this->resolveFrom();
        $message->to($to)->subject($subject)->from($from['address'], $from['name']);

        if ($replyTo = $this->resolveReplyTo()) {
            $message->replyTo($replyTo);
        }
    }
}
