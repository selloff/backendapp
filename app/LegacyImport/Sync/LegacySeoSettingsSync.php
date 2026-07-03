<?php

namespace App\LegacyImport\Sync;

use App\LegacyImport\Data\LegacySeoSettings;
use App\Services\Platform\PlatformSettingsService;

class LegacySeoSettingsSync
{
    public function sync(): void
    {
        $values = LegacySeoSettings::values();
        $settings = app(PlatformSettingsService::class);

        $settings->upsertMany([
            'google_analytics' => $values['google_analytics'],
        ], 'seo');

        $settings->upsertMany([
            'sitemap_frequency' => $values['sitemap_frequency'],
            'sitemap_last_modification' => $values['sitemap_last_modification'],
            'sitemap_priority' => $values['sitemap_priority'],
        ], 'product');

        $settings->flushCache();
    }
}
