<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Language;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can save currency converter settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/settings', [
        'group' => 'payment',
        'settings' => [
            'default_currency' => 'NGN',
            'currency_converter' => true,
            'auto_update_exchange_rates' => true,
            'currency_converter_api' => 'fixer',
            'currency_converter_api_key' => 'test-key',
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.currency_converter', true)
        ->assertJsonPath('data.settings.default_currency', 'NGN');

    $this->getJson('/api/v1/preferences/marketplace')
        ->assertOk()
        ->assertJsonPath('data.default_currency', 'NGN')
        ->assertJsonPath('data.currency_converter_enabled', true);
});

test('admin can manage languages and translations', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $english = Language::query()->where('code', 'en')->firstOrFail();

    $this->getJson('/api/v1/admin/languages')
        ->assertOk()
        ->assertJsonFragment(['code' => 'en']);

    $this->postJson('/api/v1/admin/languages', [
        'name' => 'Hausa',
        'code' => 'ha',
        'language_code' => 'ha-NG',
        'text_direction' => 'ltr',
        'language_order' => 3,
        'text_editor_lang' => 'ha',
        'is_default' => false,
        'status' => true,
    ], superAdminPinHeaders())
        ->assertCreated()
        ->assertJsonPath('data.code', 'ha');

    $this->postJson("/api/v1/admin/languages/{$english->id}/translations", [
        'label' => 'welcome_message',
        'translation' => 'Welcome to Selloff',
    ], superAdminPinHeaders())
        ->assertCreated()
        ->assertJsonPath('data.label', 'welcome_message');

    $this->getJson("/api/v1/admin/languages/{$english->id}/translations")
        ->assertOk()
        ->assertJsonFragment(['label' => 'welcome_message', 'translation' => 'Welcome to Selloff']);
});
