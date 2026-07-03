<?php

namespace App\Modules\Selloff\Escrow\Support;

use App\Services\Platform\PlatformSettingsService;

class EscrowMailBranding
{
    /**
     * @return array<string, string>
     */
    public function resolve(): array
    {
        $platform = app(PlatformSettingsService::class)->all();
        $spaBase = rtrim((string) config('selloff.spa_url', config('app.url')), '/');
        $logoPath = (string) ($platform['site_logo_url'] ?? config('selloff.platform_defaults.site_logo_url', '/selloff-logo.png'));
        $logoUrl = str_starts_with($logoPath, 'http') ? $logoPath : $spaBase.'/'.ltrim($logoPath, '/');

        $branding = config('selloff.mail_branding', []);

        return [
            'logo_url' => $logoUrl,
            'site_url' => $spaBase,
            'site_name' => (string) ($platform['site_title'] ?? 'Selloff.ng'),
            'primary' => (string) ($branding['primary'] ?? '#0075bb'),
            'escrow_cta' => (string) ($branding['escrow_cta'] ?? '#D75A07'),
            'success_cta' => (string) ($branding['success_cta'] ?? '#008D59'),
            'confirm_cta' => (string) ($branding['confirm_cta'] ?? '#68B503'),
            'muted_box' => (string) ($branding['muted_box'] ?? '#F3F3F3'),
            'alert' => (string) ($branding['alert'] ?? '#E10000'),
            'emphasis' => (string) ($branding['emphasis'] ?? '#CE0000'),
            'contact_email' => (string) ($platform['contact_email'] ?? 'support@selloff.ng'),
            'escrow_email' => (string) config('selloff.escrow_admin_email', 'escrow@selloff.ng'),
        ];
    }
}
