<?php

use App\Support\PlatformSettingsNormalizer;

test('normalizes production like visual settings', function () {
    $normalized = PlatformSettingsNormalizer::normalize([
        'primary_color' => '#222222',
        'site_logo_url' => 'uploads/logo/logo_644363d971e8b4.png',
        'storage' => 'aws_s3',
        'aws_bucket' => 'selloffng',
        'aws_region' => 'eu-west-2',
    ]);

    expect($normalized['primary_color'])->toBe('#0075bb');
    expect($normalized['site_logo_url'])->toBe('https://selloffng.s3.eu-west-2.amazonaws.com/uploads/logo/logo_644363d971e8b4.png');
});
