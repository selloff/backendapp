<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndContactTurnstileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_login_requires_turnstile_token_when_enabled(): void
    {
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
    }

    public function test_login_verifies_turnstile_token_when_enabled(): void
    {
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
    }

    public function test_forgot_password_requires_turnstile_token_when_enabled(): void
    {
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
    }

    public function test_guest_contact_requires_turnstile_token_when_enabled(): void
    {
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
    }

    public function test_authenticated_contact_skips_turnstile_when_enabled(): void
    {
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
    }

    public function test_guest_contact_verifies_turnstile_token_when_enabled(): void
    {
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
    }
}
