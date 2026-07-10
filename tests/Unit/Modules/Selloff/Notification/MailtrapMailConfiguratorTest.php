<?php

use App\Modules\Selloff\Notification\Services\MailtrapMailConfigurator;
use App\Services\Platform\PlatformSettingsService;

test('mailtrap auto mode uses sandbox outside production', function () {
    $configurator = app(MailtrapMailConfigurator::class);

    expect($configurator->resolveMode(['mailtrap_mode' => 'auto']))->toBe('sandbox');

    $smtp = $configurator->smtpConfig(['mailtrap_mode' => 'auto']);

    expect($smtp['host'])->toBe('sandbox.smtp.mailtrap.io')
        ->and($smtp['port'])->toBe(2525);
});

test('mailtrap sending mode uses live smtp host', function () {
    $configurator = app(MailtrapMailConfigurator::class);

    expect($configurator->resolveMode(['mailtrap_mode' => 'sending']))->toBe('sending');

    $smtp = $configurator->smtpConfig(['mailtrap_mode' => 'sending']);

    expect($smtp['host'])->toBe('live.smtp.mailtrap.io')
        ->and($smtp['port'])->toBe(587);
});

test('mailtrap credentials fall back to env values', function () {
    config([
        'selloff.mail.mailtrap_sandbox_username' => 'env-sandbox-user',
        'selloff.mail.mailtrap_sandbox_password' => 'env-sandbox-pass',
    ]);

    $configurator = app(MailtrapMailConfigurator::class);
    $smtp = $configurator->smtpConfig(['mailtrap_mode' => 'sandbox']);

    expect($smtp['username'])->toBe('env-sandbox-user')
        ->and($smtp['password'])->toBe('env-sandbox-pass');
});
