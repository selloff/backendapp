<?php

use App\Models\PlatformSetting;
use App\Services\Media\Upload\MediaUploadPlatformSettings;
use App\Services\Media\Upload\WatermarkService;
use App\Services\Platform\PlatformSettingsService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('reads watermark settings from platform settings', function () {
    seedPlatformSettings_in_MediaUploadPlatformSettings([
        'watermark_text' => 'Selloff.ng',
        'watermark_font_size' => 32,
        'watermark_product_enabled' => true,
        'watermark_blog_enabled' => false,
        'watermark_thumbnail_enabled' => true,
        'watermark_horizontal_align' => 'left',
        'watermark_vertical_align' => 'center',
    ]);

    $settings = app(MediaUploadPlatformSettings::class)->watermark();

    expect($settings['text'])->toBe('Selloff.ng');
    expect($settings['font_size'])->toBe(32.0);
    expect($settings['product_enabled'])->toBeTrue();
    expect($settings['blog_enabled'])->toBeFalse();
    expect($settings['thumbnail_enabled'])->toBeTrue();
    expect($settings['horizontal'])->toBe('left');
    expect($settings['vertical'])->toBe('middle');
});

test('reads image file format from platform settings', function () {
    seedPlatformSettings_in_MediaUploadPlatformSettings([
        'image_file_format' => 'PNG',
    ]);

    expect(app(MediaUploadPlatformSettings::class)->imageFormat())->toBe('PNG');
});

test('watermark service uses platform settings over env defaults', function () {
    config([
        'media_uploads.watermark.product_enabled' => false,
        'media_uploads.watermark.text' => 'env-only',
    ]);

    seedPlatformSettings_in_MediaUploadPlatformSettings([
        'watermark_text' => 'From DB',
        'watermark_product_enabled' => true,
    ]);

    $service = app(WatermarkService::class);

    expect($service->shouldApply('product', 960))->toBeTrue();
});

test('falls back to config when platform settings missing', function () {
    config([
        'media_uploads.image_format' => 'JPG',
        'media_uploads.watermark.text' => 'Fallback',
        'media_uploads.watermark.product_enabled' => true,
    ]);

    $mediaSettings = app(MediaUploadPlatformSettings::class);

    expect($mediaSettings->imageFormat())->toBe('JPG');
    expect($mediaSettings->watermark()['text'])->toBe('Fallback');
    expect($mediaSettings->watermark()['product_enabled'])->toBeTrue();
});

/**
 * @param  array<string, mixed>  $values
 */
function seedPlatformSettings_in_MediaUploadPlatformSettings(array $values): void
{
    foreach ($values as $key => $value) {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => str_starts_with($key, 'watermark_') ? 'visual' : 'product',
            ],
        );
    }

    app(PlatformSettingsService::class)->flushCache();
}