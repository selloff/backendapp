<?php

use App\Modules\Selloff\Support\Models\ContactMessage;
use App\Modules\Selloff\Support\Services\ContactMessageNotificationService;
use App\Services\Platform\PlatformSettingsService;

afterEach(function () {
    Mockery::close();
});

test('resolve reply from prefers platform contact email', function () {
    $settings = Mockery::mock(PlatformSettingsService::class);
    $settings->shouldReceive('all')->once()->andReturn([
        'contact_email' => 'support@selloff.ng',
        'mail_from_name' => 'Selloff Support',
    ]);

    $service = new ContactMessageNotificationService(
        $settings,
        Mockery::mock(\App\Modules\Selloff\Notification\Services\PlatformMailService::class),
    );

    expect($service->resolveReplyFrom())->toBe([
        'address' => 'support@selloff.ng',
        'name' => 'Selloff Support',
    ]);
});

test('resolve reply from falls back to config default', function () {
    $settings = Mockery::mock(PlatformSettingsService::class);
    $settings->shouldReceive('all')->once()->andReturn([
        'contact_email' => '',
        'mail_from_address' => 'noreply@selloff.test',
        'site_name' => 'Selloff',
    ]);

    config(['selloff.platform_settings.contact_email' => 'support@selloff.ng']);

    $service = new ContactMessageNotificationService(
        $settings,
        Mockery::mock(\App\Modules\Selloff\Notification\Services\PlatformMailService::class),
    );

    expect($service->resolveReplyFrom())->toBe([
        'address' => 'support@selloff.ng',
        'name' => 'Selloff',
    ]);
});

test('build reply subject prefixes re once', function () {
    $service = new ContactMessageNotificationService(
        Mockery::mock(PlatformSettingsService::class),
        Mockery::mock(\App\Modules\Selloff\Notification\Services\PlatformMailService::class),
    );

    $message = new ContactMessage([
        'subject' => 'Need help with my order',
    ]);

    expect($service->buildReplySubject($message))->toBe('Re: Need help with my order');

    $message->subject = 'Re: Need help with my order';
    expect($service->buildReplySubject($message))->toBe('Re: Need help with my order');

    $message->subject = null;
    expect($service->buildReplySubject($message))->toBe('Re: Contact message');
});
