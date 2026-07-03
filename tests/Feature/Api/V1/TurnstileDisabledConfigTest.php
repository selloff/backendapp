<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnstileDisabledConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_turnstile_disabled_env_overrides_database_setting(): void
    {
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
        $this->assertFalse((bool) ($settings['turnstile_status'] ?? true));
    }
}
