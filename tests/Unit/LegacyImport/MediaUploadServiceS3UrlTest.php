<?php

use App\Services\Media\MediaUploadService;
use Illuminate\Support\Facades\Config;

test('aws s3 disk builds s3 url from bucket config', function () {
    Config::set('filesystems.disks.s3.bucket', 'selloff-prod');
    Config::set('filesystems.disks.s3.region', 'eu-west-2');
    Config::set('filesystems.disks.s3.url', null);

    $url = app(MediaUploadService::class)->urlFor(
        '202604/img_w480_example.webp',
        'aws_s3',
    );

    expect($url)->toBe('https://selloff-prod.s3.eu-west-2.amazonaws.com/uploads/images/202604/img_w480_example.webp');
});

test('aws url is used when configured', function () {
    Config::set('filesystems.disks.s3.bucket', 'selloff-prod');
    Config::set('filesystems.disks.s3.region', 'us-east-1');
    Config::set('filesystems.disks.s3.url', 'https://cdn.example.com');

    $url = app(MediaUploadService::class)->urlFor(
        'uploads/images/202604/img_w480_example.webp',
        'aws_s3',
    );

    expect($url)->toStartWith('https://cdn.example.com/uploads/images/202604/img_w480_example.webp');
});

test('paths already prefixed with uploads are not doubled', function () {
    Config::set('filesystems.disks.s3.bucket', 'selloff-prod');
    Config::set('filesystems.disks.s3.region', 'eu-west-2');

    $url = app(MediaUploadService::class)->urlFor(
        'uploads/profile/avatar.webp',
        'aws_s3',
    );

    expect($url)->toEndWith('/uploads/profile/avatar.webp');
});
