<?php

use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('registration succeeds without turnstile when disabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => false,
    ]);

    $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Turn',
        'last_name' => 'Stile',
        'email' => 'turnstile.off@selloff.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated();
});

test('registration requires turnstile token when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Turn',
        'last_name' => 'Stile',
        'email' => 'turnstile.missing@selloff.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cf_turnstile_response']);
});

test('registration verifies turnstile token when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true]),
    ]);

    $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Turn',
        'last_name' => 'Stile',
        'email' => 'turnstile.ok@selloff.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'cf_turnstile_response' => 'valid-turnstile-token',
    ])->assertCreated();

    Http::assertSent(fn ($request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
        && $request['secret'] === 'secret-test-key'
        && $request['response'] === 'valid-turnstile-token');
});

test('registration rejects invalid turnstile token when enabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'turnstile_status' => true,
        'turnstile_site_key' => 'site-test-key',
        'turnstile_secret_key' => 'secret-test-key',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false]),
    ]);

    $this->postJson('/api/v1/auth/register', [
        'first_name' => 'Turn',
        'last_name' => 'Stile',
        'email' => 'turnstile.bad@selloff.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'cf_turnstile_response' => 'invalid-turnstile-token',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cf_turnstile_response']);
});
