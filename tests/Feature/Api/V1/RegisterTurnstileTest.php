<?php

namespace Tests\Feature\Api\V1;

use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegisterTurnstileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_registration_succeeds_without_turnstile_when_disabled(): void
    {
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
    }

    public function test_registration_requires_turnstile_token_when_enabled(): void
    {
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
    }

    public function test_registration_verifies_turnstile_token_when_enabled(): void
    {
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
    }

    public function test_registration_rejects_invalid_turnstile_token_when_enabled(): void
    {
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
    }
}
