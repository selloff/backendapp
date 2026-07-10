<?php

namespace App\Modules\Selloff\Notification\Support;

use App\Services\Platform\PlatformSettingsService;

class TransactionalMailBranding
{
    /**
     * @return array<string, string>
     */
    public function resolve(): array
    {
        $platform = app(PlatformSettingsService::class)->all();
        $spaBase = rtrim((string) config('selloff.spa_url', config('app.url')), '/');
        $logoPath = (string) ($platform['site_logo_email_url'] ?? $platform['site_logo_url'] ?? '/selloff-logo.png');
        $logoUrl = str_starts_with($logoPath, 'http') ? $logoPath : $spaBase.'/'.ltrim($logoPath, '/');

        $branding = config('selloff.mail_branding', []);

        return [
            'logo_url' => $logoUrl,
            'site_url' => $spaBase,
            'site_name' => (string) ($platform['site_title'] ?? $platform['application_name'] ?? 'Selloff'),
            'primary' => (string) ($platform['primary_color'] ?? $branding['primary'] ?? '#0075bb'),
            'contact_address' => (string) ($platform['contact_address'] ?? ''),
            'copyright' => (string) ($platform['copyright'] ?? '© Selloff. All rights reserved.'),
            'contact_email' => (string) ($platform['contact_email'] ?? 'support@selloff.ng'),
        ];
    }
}
