<?php

namespace Tests\Unit\Media;

use App\Services\Media\MediaUploadService;
use App\Services\Media\Upload\MediaUploadRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_variant_path_rewrites_width_prefix(): void
    {
        $service = app(MediaUploadService::class);

        $defaultPath = '202604/img_w960_abc123.webp';

        $this->assertSame(
            '202604/img_w480_abc123.webp',
            $service->productVariantPath($defaultPath, 'small'),
        );
        $this->assertSame(
            '202604/img_w1600_abc123.webp',
            $service->productVariantPath($defaultPath, 'big'),
        );
        $this->assertSame(
            $defaultPath,
            $service->productVariantPath($defaultPath, 'default'),
        );
    }

    public function test_product_variant_path_leaves_external_urls_unchanged(): void
    {
        $service = app(MediaUploadService::class);
        $url = 'https://images.unsplash.com/photo-123.jpg';

        $this->assertSame($url, $service->productVariantPath($url, 'small'));
    }

    public function test_url_for_product_image_builds_variant_urls_on_s3(): void
    {
        config([
            'filesystems.disks.s3.bucket' => 'selloff-prod',
            'filesystems.disks.s3.region' => 'eu-west-2',
            'filesystems.disks.s3.url' => null,
        ]);

        $service = app(MediaUploadService::class);
        $url = $service->urlForProductImage('202604/img_w960_abc123.webp', 'aws_s3', 'small');

        $this->assertSame(
            'https://selloff-prod.s3.eu-west-2.amazonaws.com/uploads/images/202604/img_w480_abc123.webp',
            $url,
        );
    }

    public function test_profile_upload_stores_optimized_image_under_uploads_profile(): void
    {
        Storage::fake('public');
        config(['selloff.media_disk' => 'public']);

        $service = app(MediaUploadService::class);
        $result = $service->upload(UploadedFile::fake()->image('avatar.jpg', 800, 800), 'profile');

        $this->assertSame('profile', $result['context']);
        $this->assertStringStartsWith('uploads/profile/', $result['path']);
        $this->assertStringContainsString('profile_', $result['filename']);
        Storage::disk('public')->assertExists($result['path']);
    }

    public function test_product_upload_returns_legacy_relative_path_and_variants(): void
    {
        Storage::fake('public');
        config(['selloff.media_disk' => 'public']);

        $service = app(MediaUploadService::class);
        $result = $service->upload(UploadedFile::fake()->image('product.jpg', 2000, 2000), 'product');

        $this->assertStringNotContainsString('uploads/images/', $result['path']);
        $this->assertMatchesRegularExpression('/^\d{6}\/img_w\d+_/', $result['path']);
        $this->assertArrayHasKey('variants', $result);
        $this->assertArrayHasKey('small', $result['variants']);
        $this->assertArrayHasKey('default', $result['variants']);
        $this->assertArrayHasKey('big', $result['variants']);

        foreach ($result['variants'] as $variant) {
            $storagePath = 'uploads/images/'.$variant['path'];
            Storage::disk('public')->assertExists($storagePath);
        }
    }

    public function test_vendor_document_upload_uses_support_folder_without_date_segment(): void
    {
        Storage::fake('public');
        config(['selloff.media_disk' => 'public']);

        $service = app(MediaUploadService::class);
        $result = $service->upload(
            UploadedFile::fake()->create('id-card.pdf', 100, 'application/pdf'),
            'vendor_document',
        );

        $this->assertStringStartsWith('uploads/support/file_', $result['path']);
        $this->assertStringNotContainsString('/'.now()->format('Ym').'/', $result['path']);
        Storage::disk('public')->assertExists($result['path']);
    }

    public function test_digital_context_alias_maps_to_digital_file_folder(): void
    {
        Storage::fake('public');
        config(['selloff.media_disk' => 'public']);

        $service = app(MediaUploadService::class);
        $result = $service->upload(
            UploadedFile::fake()->create('bundle.zip', 100, 'application/zip'),
            'digital',
        );

        $this->assertSame('digital_file', $result['context']);
        $this->assertStringStartsWith('uploads/digital-files/digital-file-', $result['path']);
        Storage::disk('public')->assertExists($result['path']);
    }

    public function test_slider_upload_stores_optimized_image_under_uploads_slider(): void
    {
        Storage::fake('public');
        config(['selloff.media_disk' => 'public']);

        $service = app(MediaUploadService::class);
        $desktop = $service->upload(UploadedFile::fake()->image('slide.jpg', 2000, 800), 'slider');
        $mobile = $service->upload(UploadedFile::fake()->image('slide-mobile.jpg', 800, 600), 'slider', 'mobile');

        $this->assertSame('slider', $desktop['context']);
        $this->assertStringStartsWith('uploads/slider/', $desktop['path']);
        $this->assertStringContainsString('slider_', $desktop['filename']);
        Storage::disk('public')->assertExists($desktop['path']);

        $this->assertSame('slider', $mobile['context']);
        $this->assertStringStartsWith('uploads/slider/', $mobile['path']);
        Storage::disk('public')->assertExists($mobile['path']);
    }

    public function test_registry_exposes_all_legacy_upload_contexts(): void
    {
        $contexts = MediaUploadRegistry::contexts();

        foreach ([
            'temp', 'product', 'profile', 'blog', 'category', 'slider', 'newsletter',
            'logo', 'favicon', 'attachment', 'vendor_document', 'digital_file',
        ] as $expected) {
            $this->assertContains($expected, $contexts, "Missing upload context [{$expected}]");
        }
    }
}
