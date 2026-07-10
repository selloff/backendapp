<?php

use App\Models\User;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('login requires turnstile token when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => config('app.demo_member_email', 'buyer@selloff.test'),
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cf_turnstile_response']);
});

test('login verifies turnstile token when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => config('app.demo_member_email', 'buyer@selloff.test'),
        'password' => 'password',
        'cf_turnstile_response' => 'valid-turnstile-token',
    ])->assertOk();
});

test('forgot password requires turnstile token when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'buyer@selloff.test',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cf_turnstile_response']);
});

test('guest contact requires turnstile token when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    $this->postJson('/api/v1/contact', [
        'name' => 'Guest User',
        'email' => 'guest@selloff.test',
        'subject' => 'Help',
        'message' => 'Need assistance with my order.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cf_turnstile_response']);
});

test('authenticated contact skips turnstile when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/contact', [
        'name' => 'Buyer User',
        'email' => 'buyer@selloff.test',
        'subject' => 'Help',
        'message' => 'Need assistance with my order.',
    ])->assertCreated();
});

test('guest contact verifies turnstile token when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $this->postJson('/api/v1/contact', [
        'name' => 'Guest User',
        'email' => 'guest@selloff.test',
        'subject' => 'Help',
        'message' => 'Need assistance with my order.',
        'cf_turnstile_response' => 'valid-turnstile-token',
    ])->assertCreated();
});
