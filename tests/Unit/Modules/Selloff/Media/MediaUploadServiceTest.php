<?php

use App\Services\Media\MediaUploadService;
use App\Services\Media\Upload\MediaUploadRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('product variant path rewrites width prefix', function () {
    $service = app(MediaUploadService::class);

    $defaultPath = '202604/img_w960_abc123.webp';

    expect($service->productVariantPath($defaultPath, 'small'))->toBe('202604/img_w480_abc123.webp');
    expect($service->productVariantPath($defaultPath, 'big'))->toBe('202604/img_w1600_abc123.webp');
    expect($service->productVariantPath($defaultPath))->toBe($defaultPath);
});

test('product variant path leaves external urls unchanged', function () {
    $service = app(MediaUploadService::class);
    $url = 'https://images.unsplash.com/photo-123.jpg';

    expect($service->productVariantPath($url, 'small'))->toBe($url);
});

test('url for product image builds variant urls on s3', function () {
    config([
        'filesystems.disks.s3.bucket' => 'selloff-prod',
        'filesystems.disks.s3.region' => 'eu-west-2',
        'filesystems.disks.s3.url' => null,
    ]);

    $service = app(MediaUploadService::class);
    $url = $service->urlForProductImage('202604/img_w960_abc123.webp', 'aws_s3', 'small');

    expect($url)->toBe('https://selloff-prod.s3.eu-west-2.amazonaws.com/uploads/images/202604/img_w480_abc123.webp');
});

test('profile upload stores optimized image under uploads profile', function () {
    Storage::fake('public');
    config(['selloff.media_disk' => 'public']);

    $service = app(MediaUploadService::class);
    $result = $service->upload(UploadedFile::fake()->image('avatar.jpg', 800, 800), 'profile');

    expect($result['context'])->toBe('profile');
    expect($result['path'])->toStartWith('uploads/profile/');
    $this->assertStringContainsString('profile_', $result['filename']);
    Storage::disk('public')->assertExists($result['path']);
});

test('product upload returns legacy relative path and variants', function () {
    Storage::fake('public');
    config(['selloff.media_disk' => 'public']);

    $service = app(MediaUploadService::class);
    $result = $service->upload(UploadedFile::fake()->image('product.jpg', 2000, 2000), 'product');

    $this->assertStringNotContainsString('uploads/images/', $result['path']);
    expect($result['path'])->toMatch('/^\d{6}\/img_w\d+_/');
    expect($result)->toHaveKey('variants');
    expect($result['variants'])->toHaveKey('small');
    expect($result['variants'])->toHaveKey('default');
    expect($result['variants'])->toHaveKey('big');

    $tokens = [];
    foreach ($result['variants'] as $label => $variant) {
        expect($variant['path'])->toMatch('/^\d{6}\/img_w\d+_/');
        preg_match('/img_w\d+_([^.]+)\./', $variant['path'], $matches);
        $tokens[$label] = $matches[1] ?? null;
        $storagePath = 'uploads/images/'.$variant['path'];
        Storage::disk('public')->assertExists($storagePath);
    }

    expect(array_unique(array_values($tokens)))->toHaveCount(1);
});

test('vendor document upload uses support folder without date segment', function () {
    Storage::fake('public');
    config(['selloff.media_disk' => 'public']);

    $service = app(MediaUploadService::class);
    $result = $service->upload(
        UploadedFile::fake()->create('id-card.pdf', 100, 'application/pdf'),
        'vendor_document',
    );

    expect($result['path'])->toStartWith('uploads/support/file_');
    $this->assertStringNotContainsString('/'.now()->format('Ym').'/', $result['path']);
    Storage::disk('public')->assertExists($result['path']);
});

test('digital context alias maps to digital file folder', function () {
    Storage::fake('public');
    config(['selloff.media_disk' => 'public']);

    $service = app(MediaUploadService::class);
    $result = $service->upload(
        UploadedFile::fake()->create('bundle.zip', 100, 'application/zip'),
        'digital',
    );

    expect($result['context'])->toBe('digital_file');
    expect($result['path'])->toStartWith('uploads/digital-files/digital-file-');
    Storage::disk('public')->assertExists($result['path']);
});

test('slider upload stores optimized image under uploads slider', function () {
    Storage::fake('public');
    config(['selloff.media_disk' => 'public']);

    $service = app(MediaUploadService::class);
    $desktop = $service->upload(UploadedFile::fake()->image('slide.jpg', 2000, 800), 'slider');
    $mobile = $service->upload(UploadedFile::fake()->image('slide-mobile.jpg', 800, 600), 'slider', 'mobile');

    expect($desktop['context'])->toBe('slider');
    expect($desktop['path'])->toStartWith('uploads/slider/');
    $this->assertStringContainsString('slider_', $desktop['filename']);
    Storage::disk('public')->assertExists($desktop['path']);

    expect($mobile['context'])->toBe('slider');
    expect($mobile['path'])->toStartWith('uploads/slider/');
    Storage::disk('public')->assertExists($mobile['path']);
});

test('product upload stores files when media disk uses aws_s3 alias', function () {
    Storage::fake('s3');
    config(['selloff.media_disk' => 'aws_s3']);

    $service = app(MediaUploadService::class);
    $result = $service->upload(UploadedFile::fake()->image('product.jpg', 2000, 2000), 'product');

    expect($result['disk'])->toBe('aws_s3');
    expect($result['path'])->toMatch('/^\d{6}\/img_w\d+_/');

    $storagePath = 'uploads/images/'.$result['path'];
    Storage::disk('s3')->assertExists($storagePath);
});

test('support document resolver finds files on s3 with legacy path aliases', function () {
    Storage::fake('s3');
    config(['selloff.media_disk' => 's3']);

    $storagePath = 'uploads/support/file_demo_id.jpg';
    Storage::disk('s3')->put($storagePath, 'image-bytes');

    $service = app(MediaUploadService::class);
    $resolved = $service->resolveReadableSupportDocument('support/file_demo_id.jpg', 'aws_s3');

    expect($resolved)->toBe(['disk' => 's3', 'path' => $storagePath]);
    expect($service->supportDocumentPathsMatch(
        'uploads/support/file_demo_id.jpg',
        'support/file_demo_id.jpg',
    ))->toBeTrue();
});

test('support document resolver finds legacy dated support folder objects', function () {
    Storage::fake('s3');
    config(['selloff.media_disk' => 's3']);

    $filename = 'file_68b13dafe67e48-18174823-39576584.jpg';
    $storagePath = 'uploads/support/202607/'.$filename;
    Storage::disk('s3')->put($storagePath, 'image-bytes');

    $service = app(MediaUploadService::class);
    $resolved = $service->resolveReadableSupportDocument('uploads/support/'.$filename);

    expect($resolved)->toBe(['disk' => 's3', 'path' => $storagePath]);
});

test('support document public url mirrors legacy base_url uploads/support links', function () {
    config(['selloff.legacy_media_public_url' => 'https://selloff.ng']);

    $service = app(MediaUploadService::class);

    expect($service->supportDocumentPublicUrl('uploads/support/file_demo.jpg'))
        ->toBe('https://selloff.ng/uploads/support/file_demo.jpg');
});

test('support document resolver fetches from legacy media public url when disk lookup fails', function () {
    Storage::fake('public');
    Storage::fake('s3');
    Http::fake([
        'https://selloff.ng/*' => Http::response(
            'image-bytes',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
        '*' => Http::response('', 404),
    ]);

    config([
        'selloff.media_disk' => 'public',
        'selloff.legacy_media_public_url' => 'https://selloff.ng',
    ]);

    $service = app(MediaUploadService::class);
    $resolved = $service->resolveInlineSupportDocument('uploads/support/file_68b13dafe67e48-18174823-39576584.jpg');

    expect($resolved)->toBe([
        'type' => 'contents',
        'content' => 'image-bytes',
        'mime' => 'image/jpeg',
    ]);
});

test('registry exposes all legacy upload contexts', function () {
    $contexts = MediaUploadRegistry::contexts();

    foreach ([
        'temp', 'product', 'profile', 'blog', 'category', 'slider', 'newsletter',
        'logo', 'favicon', 'attachment', 'vendor_document', 'digital_file',
    ] as $expected) {
        expect($contexts)->toContain($expected);
    }
});