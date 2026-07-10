<?php

use App\Services\Platform\PlatformSettingsService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('public platform brand does not expose oauth secrets', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'google_client_id' => 'public-google-client-id',
        'google_client_secret' => 'super-secret-google-value',
        'facebook_app_secret' => 'super-secret-facebook-value',
        'smtp_password' => 'mail-password',
    ], 'social_login');

    $response = $this->getJson('/api/v1/public/platform-brand')->assertOk();

    $settings = $response->json('data.platform_settings');

    expect($settings['google_client_id'])->toBe('public-google-client-id');
    $this->assertArrayNotHasKey('google_client_secret', $settings);
    $this->assertArrayNotHasKey('facebook_app_secret', $settings);
    $this->assertArrayNotHasKey('smtp_password', $settings);
});

test('auth me does not expose oauth secrets', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'google_client_secret' => 'super-secret-google-value',
    ], 'social_login');

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => config('app.demo_member_email', 'buyer@selloff.test'),
        'password' => 'password',
        'device_name' => 'test',
    ])->assertOk();

    $token = $login->json('data.token');

    $response = $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

    $settings = $response->json('data.platform_settings');

    $this->assertArrayNotHasKey('google_client_secret', $settings);
});
