<?php

use App\LegacyImport\Sync\LegacySeoSettingsSync;
use App\Models\PlatformSetting;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('sync imports legacy seo settings', function () {
    app(LegacySeoSettingsSync::class)->sync();

    $analytics = PlatformSetting::query()->where('key', 'google_analytics')->value('value');
    expect($analytics)->toBeString();
    $this->assertStringContainsString('Facebook Pixel Code', $analytics);

    expect(PlatformSetting::query()->where('key', 'sitemap_frequency')->value('value'))->toBe('none');
    expect(PlatformSetting::query()->where('key', 'sitemap_last_modification')->value('value'))->toBe('none');
    expect(PlatformSetting::query()->where('key', 'sitemap_priority')->value('value'))->toBe('none');
});
