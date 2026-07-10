<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Services\Platform\PlatformSettingsService;

class MailtrapMailConfigurator
{
    public function resolveMode(?array $settings = null): string
    {
        $settings ??= app(PlatformSettingsService::class)->all();
        $mode = (string) ($settings['mailtrap_mode']
            ?? config('selloff.mail.mailtrap_mode', 'auto'));

        if ($mode === 'auto') {
            return app()->environment('production') ? 'sending' : 'sandbox';
        }

        return in_array($mode, ['sandbox', 'sending'], true) ? $mode : 'sandbox';
    }

    /**
     * @return array{host: string, port: int, username: ?string, password: ?string, encryption: string}
     */
    public function smtpConfig(?array $settings = null): array
    {
        $settings ??= app(PlatformSettingsService::class)->all();

        if ($this->resolveMode($settings) === 'sending') {
            return [
                'host' => 'live.smtp.mailtrap.io',
                'port' => 587,
                'username' => $this->credential($settings, 'mailtrap_sending_username', 'MAILTRAP_SENDING_USERNAME'),
                'password' => $this->credential($settings, 'mailtrap_sending_password', 'MAILTRAP_SENDING_PASSWORD'),
                'encryption' => 'tls',
            ];
        }

        return [
            'host' => 'sandbox.smtp.mailtrap.io',
            'port' => 2525,
            'username' => $this->credential($settings, 'mailtrap_sandbox_username', 'MAILTRAP_SANDBOX_USERNAME'),
            'password' => $this->credential($settings, 'mailtrap_sandbox_password', 'MAILTRAP_SANDBOX_PASSWORD'),
            'encryption' => 'tls',
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function credential(array $settings, string $settingsKey, string $envKey): ?string
    {
        $value = $settings[$settingsKey] ?? config("selloff.mail.{$settingsKey}") ?? env($envKey);

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
