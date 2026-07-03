<?php

namespace Tests\Unit\Support;

use App\Support\PlatformSettingsNormalizer;
use Tests\TestCase;

class PlatformSettingsNormalizerTest extends TestCase
{
    public function test_normalizes_production_like_visual_settings(): void
    {
        $normalized = PlatformSettingsNormalizer::normalize([
            'primary_color' => '#222222',
            'site_logo_url' => 'uploads/logo/logo_644363d971e8b4.png',
            'storage' => 'aws_s3',
            'aws_bucket' => 'selloffng',
            'aws_region' => 'eu-west-2',
        ]);

        $this->assertSame('#0075bb', $normalized['primary_color']);
        $this->assertSame(
            'https://selloffng.s3.eu-west-2.amazonaws.com/uploads/logo/logo_644363d971e8b4.png',
            $normalized['site_logo_url'],
        );
    }
}
