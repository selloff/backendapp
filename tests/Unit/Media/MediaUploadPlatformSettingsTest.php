<?php

namespace Tests\Unit\Media;

use App\Models\PlatformSetting;
use App\Services\Media\Upload\MediaUploadPlatformSettings;
use App\Services\Media\Upload\WatermarkService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaUploadPlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_watermark_settings_from_platform_settings(): void
    {
        $this->seedPlatformSettings([
            'watermark_text' => 'Selloff.ng',
            'watermark_font_size' => 32,
            'watermark_product_enabled' => true,
            'watermark_blog_enabled' => false,
            'watermark_thumbnail_enabled' => true,
            'watermark_horizontal_align' => 'left',
            'watermark_vertical_align' => 'center',
        ]);

        $settings = app(MediaUploadPlatformSettings::class)->watermark();

        $this->assertSame('Selloff.ng', $settings['text']);
        $this->assertSame(32.0, $settings['font_size']);
        $this->assertTrue($settings['product_enabled']);
        $this->assertFalse($settings['blog_enabled']);
        $this->assertTrue($settings['thumbnail_enabled']);
        $this->assertSame('left', $settings['horizontal']);
        $this->assertSame('middle', $settings['vertical']);
    }

    public function test_reads_image_file_format_from_platform_settings(): void
    {
        $this->seedPlatformSettings([
            'image_file_format' => 'PNG',
        ]);

        $this->assertSame('PNG', app(MediaUploadPlatformSettings::class)->imageFormat());
    }

    public function test_watermark_service_uses_platform_settings_over_env_defaults(): void
    {
        config([
            'media_uploads.watermark.product_enabled' => false,
            'media_uploads.watermark.text' => 'env-only',
        ]);

        $this->seedPlatformSettings([
            'watermark_text' => 'From DB',
            'watermark_product_enabled' => true,
        ]);

        $service = app(WatermarkService::class);

        $this->assertTrue($service->shouldApply('product', 960));
    }

    public function test_falls_back_to_config_when_platform_settings_missing(): void
    {
        config([
            'media_uploads.image_format' => 'JPG',
            'media_uploads.watermark.text' => 'Fallback',
            'media_uploads.watermark.product_enabled' => true,
        ]);

        $mediaSettings = app(MediaUploadPlatformSettings::class);

        $this->assertSame('JPG', $mediaSettings->imageFormat());
        $this->assertSame('Fallback', $mediaSettings->watermark()['text']);
        $this->assertTrue($mediaSettings->watermark()['product_enabled']);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function seedPlatformSettings(array $values): void
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
}
