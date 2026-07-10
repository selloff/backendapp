<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Language;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can update product listing and general settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/settings', [
        'group' => 'product_listing',
        'settings' => [
            'marketplace_sku' => false,
            'pagination_per_page' => 48,
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.marketplace_sku', false)
        ->assertJsonPath('data.settings.pagination_per_page', 48);

    $this->putJson('/api/v1/settings', [
        'group' => 'general',
        'settings' => [
            'turnstile_status' => true,
            'turnstile_site_key' => 'site-test-key',
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.turnstile_status', true)
        ->assertJsonPath('data.settings.turnstile_site_key', 'site-test-key');
});

test('admin can update payment commerce and legacy gateway settings', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/admin/payments/gateways', [
        'commission_rate' => 7.5,
        'vat_status' => true,
        'cash_on_delivery_debt_limit' => 2500,
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.gateway_settings.commission_rate', 7.5)
        ->assertJsonPath('data.gateway_settings.vat_status', true)
        ->assertJsonPath('data.gateway_settings.cash_on_delivery_debt_limit', 2500);

    $this->putJson('/api/v1/admin/payments/gateways/legacy', [
        'name_key' => 'stripe',
        'status' => true,
        'environment' => 'sandbox',
        'public_key' => 'pk_test',
        'transaction_fee' => 2.5,
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('success', true);

    $stored = app(PlatformSettingsService::class)->all();
    $gateways = json_decode((string) ($stored['legacy_payment_gateways'] ?? '[]'), true);
    expect($gateways)->toBeArray();
    expect($gateways)->not->toBeEmpty();
});

test('admin can toggle classified currency setting and delete language', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/settings', [
        'group' => 'payment',
        'settings' => [
            'allow_all_currencies_for_classified' => true,
        ],
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.settings.allow_all_currencies_for_classified', true);

    $language = Language::query()->create([
        'name' => 'Phase 14 Test',
        'code' => 'p14',
        'status' => true,
        'is_default' => false,
    ]);

    $this->deleteJson("/api/v1/admin/languages/{$language->id}", [], adminSettingsDeleteHeaders())
        ->assertOk()
        ->assertJsonPath('data.deleted', true);

    $this->assertDatabaseMissing('languages', ['id' => $language->id]);
});
