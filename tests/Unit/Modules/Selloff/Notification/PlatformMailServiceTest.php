<?php

use App\Modules\Selloff\Notification\Services\PlatformMailService;
use App\Services\Platform\PlatformSettingsService;

test('resolve from falls back when platform from address is blank', function () {
    config(['mail.from.address' => 'env-from@selloff.test', 'mail.from.name' => 'Env From']);

    $settings = Mockery::mock(PlatformSettingsService::class);
    $settings->shouldReceive('all')->andReturn([
        'mail_from_address' => '',
        'mail_from_name' => '  ',
    ]);

    $service = new PlatformMailService($settings, app(\App\Modules\Selloff\Notification\Services\MailtrapMailConfigurator::class));

    expect($service->resolveFrom())->toBe([
        'address' => 'env-from@selloff.test',
        'name' => 'Env From',
    ]);
});

test('resolve from uses hardcoded defaults when platform and env are blank', function () {
    config(['mail.from.address' => '', 'mail.from.name' => '']);

    $settings = Mockery::mock(PlatformSettingsService::class);
    $settings->shouldReceive('all')->andReturn([
        'mail_from_address' => '',
        'mail_from_name' => '',
    ]);

    $service = new PlatformMailService($settings, app(\App\Modules\Selloff\Notification\Services\MailtrapMailConfigurator::class));

    expect($service->resolveFrom())->toBe([
        'address' => 'noreply@selloff.test',
        'name' => 'Selloff',
    ]);
});
