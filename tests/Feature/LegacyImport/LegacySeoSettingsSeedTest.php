<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Sync\LegacySeoSettingsSync;
use App\Models\PlatformSetting;
use Tests\TestCase;

class LegacySeoSettingsSeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_sync_imports_legacy_seo_settings(): void
    {
        app(LegacySeoSettingsSync::class)->sync();

        $analytics = PlatformSetting::query()->where('key', 'google_analytics')->value('value');
        $this->assertIsString($analytics);
        $this->assertStringContainsString('Facebook Pixel Code', $analytics);

        $this->assertSame('none', PlatformSetting::query()->where('key', 'sitemap_frequency')->value('value'));
        $this->assertSame('none', PlatformSetting::query()->where('key', 'sitemap_last_modification')->value('value'));
        $this->assertSame('none', PlatformSetting::query()->where('key', 'sitemap_priority')->value('value'));
    }
}
