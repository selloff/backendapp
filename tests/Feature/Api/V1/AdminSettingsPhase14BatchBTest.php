<?php

use App\Models\User;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can update email transport and options', function () {
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
    ], superAdminPinHeaders())
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
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.email_verification', false)
        ->assertJsonPath('data.settings.mail_options_account', 'ops@selloff.test');
});

test('admin can update social login and visual settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/settings', [
        'group' => 'social_login',
        'settings' => [
            'facebook_app_id' => 'fb-app',
            'facebook_app_secret' => 'fb-secret',
            'google_client_secret' => 'google-secret',
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.facebook_app_id', 'fb-app')
        ->assertJsonPath('data.settings.google_client_secret', 'google-secret');

    $this->putJson('/api/v1/settings', [
        'group' => 'visual',
        'settings' => [
            'site_logo_email_url' => 'uploads/logo-email.png',
            'logo_width' => 180,
            'logo_height' => 72,
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.logo_width', 180);

    $storedUrl = $this->getJson('/api/v1/settings?group=visual')
        ->assertOk()
        ->json('data.settings.site_logo_email_url');
    expect($storedUrl)->toContain('logo-email.png');

    $stored = app(PlatformSettingsService::class)->all();
    expect((string) ($stored['facebook_app_secret'] ?? ''))->toBe('fb-secret');
});

test('admin can send test email', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/email/test', [
        'email' => 'ops@selloff.test',
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.sent', true);
});
