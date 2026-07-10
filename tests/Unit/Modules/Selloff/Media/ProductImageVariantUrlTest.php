<?php

use App\Services\Media\MediaUploadService;

test('explicit legacy small path is used instead of width rewrite', function () {
    config([
        'filesystems.disks.s3.bucket' => 'selloff-prod',
        'filesystems.disks.s3.region' => 'eu-west-2',
        'filesystems.disks.s3.url' => null,
    ]);

    $service = app(MediaUploadService::class);

    $defaultPath = '202509/img_w960_68c1579358d0b1-57199538.png';
    $smallPath = '202509/img_w480_68c157933aa300-16140410.png';
    $variantPaths = [
        'small' => $smallPath,
        'default' => $defaultPath,
        'big' => '202509/img_w1600_68c15793733e32-99683383.png',
    ];

    $smallUrl = $service->urlForProductImageWithVariants($defaultPath, 'aws_s3', 'small', $variantPaths);
    $defaultUrl = $service->urlForProductImageWithVariants($defaultPath, 'aws_s3', 'default', $variantPaths);

    $this->assertStringContainsString($smallPath, $smallUrl);
    $this->assertStringContainsString($defaultPath, $defaultUrl);
    $this->assertStringNotContainsString('68c1579358d0b1', $smallUrl);
});

test('legacy x500 paths fall back to default when small missing', function () {
    $service = app(MediaUploadService::class);
    $defaultPath = '202306/img_x500_647dc38469fa92-51967548-87366947.jpg';

    $url = $service->urlForProductImageWithVariants($defaultPath, 'aws_s3', 'small', [
        'default' => $defaultPath,
    ]);

    $this->assertStringContainsString($defaultPath, $url);
});
