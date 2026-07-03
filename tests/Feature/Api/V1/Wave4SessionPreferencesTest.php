<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Wave4SessionPreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_marketplace_preferences_exposes_wave4_storage_keys(): void
    {
        $this->getJson('/api/v1/preferences/marketplace')
            ->assertOk()
            ->assertJsonPath('data.storage_keys.estimated_delivery_location', 'mds_estimated_delivery_location')
            ->assertJsonPath('data.storage_keys.cart_has_changed', 'mds_cart_has_changed')
            ->assertJsonPath('data.storage_keys.control_panel_lang', 'mds_control_panel_lang');
    }

    public function test_user_can_persist_selected_currency_code_on_profile(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        app(PlatformSettingsService::class)->upsertMany([
            'currency_converter' => true,
            'default_currency' => 'NGN',
        ], 'payment');

        Sanctum::actingAs($buyer);

        $this->patchJson('/api/v1/auth/me', [
            'selected_currency_code' => $usd->code,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.selected_currency_code', 'USD');

        $this->assertSame('USD', $buyer->fresh()->selected_currency_code);
    }

    public function test_user_can_update_phone_number_on_profile(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $buyer->update(['phone_number' => null]);

        Sanctum::actingAs($buyer);

        $this->patchJson('/api/v1/auth/me', [
            'phone_number' => '08012345678',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.phone_number', '08012345678');

        $this->assertSame('08012345678', $buyer->fresh()->phone_number);
    }

    public function test_cart_item_mutation_clears_applied_shipping(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $audio = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
        $phone = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $country = Country::query()->where('code', 'NG')->firstOrFail();
        $state = State::query()->where('name', 'Lagos')->firstOrFail();
        $method = ShippingMethod::query()->where('name', 'Standard Delivery')->firstOrFail();

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', ['product_id' => $audio->id, 'quantity' => 1])
            ->assertCreated();

        $this->postJson('/api/v1/cart/shipping', [
            'shipping_method_id' => $method->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.totals.shipping_cost', 1500);

        $this->postJson('/api/v1/cart/items', ['product_id' => $phone->id, 'quantity' => 1])
            ->assertCreated()
            ->assertJsonPath('data.totals.shipping_cost', 0);
    }
}
