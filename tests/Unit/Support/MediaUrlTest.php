<?php

namespace Tests\Unit\Support;

use App\Support\MediaUrl;
use Tests\TestCase;

class MediaUrlTest extends TestCase
{
    public function test_resolve_builds_absolute_url_from_app_url(): void
    {
        config([
            'app.url' => 'https://api.selloff.local',
            'selloff.image_url_prefix' => '/storage/',
        ]);

        $this->assertSame(
            'https://api.selloff.local/storage/uploads/slider/202606/slider_test.webp',
            MediaUrl::resolve('uploads/slider/202606/slider_test.webp'),
        );
    }

    public function test_resolve_keeps_external_urls_unchanged(): void
    {
        $url = 'https://cdn.example.com/slide.jpg';

        $this->assertSame($url, MediaUrl::resolve($url));
    }

    public function test_resolve_public_root_paths_from_app_url(): void
    {
        config([
            'app.url' => 'https://api.selloff.local',
            'selloff.image_url_prefix' => '/storage/',
        ]);

        $this->assertSame(
            'https://api.selloff.local/selloff-logo.png',
            MediaUrl::resolve('/selloff-logo.png'),
        );
    }

    public function test_prefix_is_absolute_when_app_url_is_set(): void
    {
        config([
            'app.url' => 'https://api.selloff.local',
            'selloff.image_url_prefix' => '/storage/',
        ]);

        $this->assertSame('https://api.selloff.local/storage', MediaUrl::prefix());
    }

    public function test_prefix_for_settings_uses_s3_when_configured(): void
    {
        $this->assertSame(
            'https://selloffng.s3.eu-west-2.amazonaws.com',
            MediaUrl::prefixForSettings([
                'storage' => 'aws_s3',
                'aws_bucket' => 'selloffng',
                'aws_region' => 'eu-west-2',
            ]),
        );
    }

    public function test_resolve_uses_custom_prefix(): void
    {
        $this->assertSame(
            'https://selloffng.s3.eu-west-2.amazonaws.com/uploads/logo/logo.png',
            MediaUrl::resolve(
                'uploads/logo/logo.png',
                'https://selloffng.s3.eu-west-2.amazonaws.com',
            ),
        );
    }
}
