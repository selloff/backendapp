<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('turnstile disabled env overrides database setting', function () {
    config(['selloff.security.turnstile_disabled' => true]);
    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-key',
        'turnstile_secret_key' => 'secret-key',
    ]);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'nobody@example.com',
    ])->assertJsonMissingValidationErrors(['cf_turnstile_response']);

    $settings = app(\App\Services\Platform\PlatformSettingsService::class)->all();
    expect((bool) ($settings['turnstile_status'] ?? true))->toBeFalse();
});