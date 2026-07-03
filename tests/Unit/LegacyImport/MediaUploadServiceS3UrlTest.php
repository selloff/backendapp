<?php

namespace Tests\Unit\LegacyImport;

use App\Services\Media\MediaUploadService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MediaUploadServiceS3UrlTest extends TestCase
{
    public function test_aws_s3_disk_builds_s3_url_from_bucket_config(): void
    {
        Config::set('filesystems.disks.s3.bucket', 'selloff-prod');
        Config::set('filesystems.disks.s3.region', 'eu-west-2');
        Config::set('filesystems.disks.s3.url', null);

        $url = app(MediaUploadService::class)->urlFor(
            '202604/img_w480_example.webp',
            'aws_s3',
        );

        $this->assertSame(
            'https://selloff-prod.s3.eu-west-2.amazonaws.com/uploads/images/202604/img_w480_example.webp',
            $url,
        );
    }

    public function test_aws_url_is_used_when_configured(): void
    {
        Config::set('filesystems.disks.s3.bucket', 'selloff-prod');
        Config::set('filesystems.disks.s3.region', 'us-east-1');
        Config::set('filesystems.disks.s3.url', 'https://cdn.example.com');

        $url = app(MediaUploadService::class)->urlFor(
            'uploads/images/202604/img_w480_example.webp',
            'aws_s3',
        );

        $this->assertStringStartsWith('https://cdn.example.com/uploads/images/202604/img_w480_example.webp', $url);
    }

    public function test_paths_already_prefixed_with_uploads_are_not_doubled(): void
    {
        Config::set('filesystems.disks.s3.bucket', 'selloff-prod');
        Config::set('filesystems.disks.s3.region', 'eu-west-2');

        $url = app(MediaUploadService::class)->urlFor(
            'uploads/profile/avatar.webp',
            'aws_s3',
        );

        $this->assertStringEndsWith('/uploads/profile/avatar.webp', $url);
    }
}
