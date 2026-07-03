<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Language;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsPhase14BatchATest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_update_product_listing_and_general_settings(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings', [
            'group' => 'product_listing',
            'settings' => [
                'marketplace_sku' => false,
                'pagination_per_page' => 48,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.marketplace_sku', false)
            ->assertJsonPath('data.settings.pagination_per_page', 48);

        $this->putJson('/api/v1/settings', [
            'group' => 'general',
            'settings' => [
                'turnstile_status' => true,
                'turnstile_site_key' => 'site-test-key',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.turnstile_status', true)
            ->assertJsonPath('data.settings.turnstile_site_key', 'site-test-key');
    }

    public function test_admin_can_update_payment_commerce_and_legacy_gateway_settings(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/payments/gateways', [
            'commission_rate' => 7.5,
            'vat_status' => true,
            'cash_on_delivery_debt_limit' => 2500,
        ])
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
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $stored = app(PlatformSettingsService::class)->all();
        $gateways = json_decode((string) ($stored['legacy_payment_gateways'] ?? '[]'), true);
        $this->assertIsArray($gateways);
        $this->assertNotEmpty($gateways);
    }

    public function test_admin_can_toggle_classified_currency_setting_and_delete_language(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings', [
            'group' => 'payment',
            'settings' => [
                'allow_all_currencies_for_classified' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.allow_all_currencies_for_classified', true);

        $language = Language::query()->create([
            'name' => 'Phase 14 Test',
            'code' => 'p14',
            'status' => true,
            'is_default' => false,
        ]);

        $this->deleteJson("/api/v1/admin/languages/{$language->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('languages', ['id' => $language->id]);
    }
}
