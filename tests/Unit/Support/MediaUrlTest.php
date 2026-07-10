<?php

use App\Support\MediaUrl;

test('resolve builds absolute url from app url', function () {
    config([
        'app.url' => 'https://api.selloff.local',
        'selloff.image_url_prefix' => '/storage/',
    ]);

    expect(MediaUrl::resolve('uploads/slider/202606/slider_test.webp'))->toBe('https://api.selloff.local/storage/uploads/slider/202606/slider_test.webp');
});

test('resolve keeps external urls unchanged', function () {
    $url = 'https://cdn.example.com/slide.jpg';

    expect(MediaUrl::resolve($url))->toBe($url);
});

test('resolve public root paths from app url', function () {
    config([
        'app.url' => 'https://api.selloff.local',
        'selloff.image_url_prefix' => '/storage/',
    ]);

    expect(MediaUrl::resolve('/selloff-logo.png'))->toBe('https://api.selloff.local/selloff-logo.png');
});

test('prefix is absolute when app url is set', function () {
    config([
        'app.url' => 'https://api.selloff.local',
        'selloff.image_url_prefix' => '/storage/',
    ]);

    expect(MediaUrl::prefix())->toBe('https://api.selloff.local/storage');
});

test('prefix for settings uses s3 when configured', function () {
    expect(MediaUrl::prefixForSettings([
        'storage' => 'aws_s3',
        'aws_bucket' => 'selloffng',
        'aws_region' => 'eu-west-2',
    ]))->toBe('https://selloffng.s3.eu-west-2.amazonaws.com');
});

test('resolve uses custom prefix', function () {
    expect(MediaUrl::resolve(
        'uploads/logo/logo.png',
        'https://selloffng.s3.eu-west-2.amazonaws.com',
    ))->toBe('https://selloffng.s3.eu-west-2.amazonaws.com/uploads/logo/logo.png');
});
