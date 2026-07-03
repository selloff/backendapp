<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsPhase14BatchBTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_update_email_transport_and_options(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings', [
            'group' => 'email',
            'settings' => [
                'mail_service' => 'mailgun',
                'mailgun_region' => 'eu',
                'mailgun_domain' => 'mg.example.com',
                'mailgun_sender_email' => 'info@mg.example.com',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.mail_service', 'mailgun')
            ->assertJsonPath('data.settings.mailgun_region', 'eu');

        $this->putJson('/api/v1/settings', [
            'group' => 'email',
            'settings' => [
                'email_verification' => false,
                'email_option_new_order' => false,
                'mail_options_account' => 'ops@selloff.test',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.email_verification', false)
            ->assertJsonPath('data.settings.mail_options_account', 'ops@selloff.test');
    }

    public function test_admin_can_update_social_login_and_visual_settings(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings', [
            'group' => 'social_login',
            'settings' => [
                'facebook_app_id' => 'fb-app',
                'facebook_app_secret' => 'fb-secret',
                'google_client_secret' => 'google-secret',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.facebook_app_id', 'fb-app')
            ->assertJsonPath('data.settings.google_client_secret', 'google-secret');

        $this->putJson('/api/v1/settings', [
            'group' => 'visual',
            'settings' => [
                'site_logo_email_url' => '/storage/logo-email.png',
                'logo_width' => 180,
                'logo_height' => 72,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.site_logo_email_url', '/storage/logo-email.png')
            ->assertJsonPath('data.settings.logo_width', 180);

        $stored = app(PlatformSettingsService::class)->all();
        $this->assertSame('fb-secret', (string) ($stored['facebook_app_secret'] ?? ''));
    }

    public function test_admin_can_send_test_email(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/email/test', [
            'email' => 'ops@selloff.test',
        ])
            ->assertOk()
            ->assertJsonPath('data.sent', true);
    }
}
