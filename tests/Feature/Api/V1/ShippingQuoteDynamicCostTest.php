<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Modules\Selloff\Shipping\Models\ShippingZone;
use App\Modules\Selloff\Shipping\Models\ShippingZoneLocation;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShippingQuoteDynamicCostTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_cart_quote_uses_weight_tiers_for_flat_rate_methods(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $country = Country::query()->where('code', 'NG')->firstOrFail();
        $state = State::query()->where('name', 'Lagos')->firstOrFail();

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $product->update([
            'vendor_id' => $vendor->id,
            'price' => 50000,
            'price_discounted' => null,
            'shipping_dimensions' => ['weight' => 2.5],
        ]);

        $zone = ShippingZone::query()->create([
            'seller_id' => $vendor->id,
            'name' => 'Weight Quote Zone',
            'status' => true,
        ]);

        ShippingZoneLocation::query()->create([
            'shipping_zone_id' => $zone->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);

        ShippingMethod::query()->create([
            'shipping_zone_id' => $zone->id,
            'name' => 'Weight Tier Rate',
            'method_type' => 'flat_rate',
            'cost_calculation_type' => 'total_weight',
            'flat_rate_costs' => [
                ['min_weight' => 0, 'max_weight' => 1, 'cost' => 500],
                ['min_weight' => 1, 'max_weight' => 5, 'cost' => 1200],
            ],
            'status' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $response = $this->getJson('/api/v1/shipping/quote?for_cart=1&country_id='.$country->id.'&state_id='.$state->id)
            ->assertOk();

        $methods = collect($response->json('data.sellers.0.methods'));
        $weightMethod = $methods->firstWhere('name', 'Weight Tier Rate');

        $this->assertNotNull($weightMethod);
        $this->assertSame(1200, $weightMethod['flat_rate']);
    }

    public function test_free_shipping_only_appears_when_minimum_order_is_met(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $country = Country::query()->where('code', 'NG')->firstOrFail();
        $state = State::query()->where('name', 'Lagos')->firstOrFail();

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $product->update([
            'vendor_id' => $vendor->id,
            'price' => 5000,
            'price_discounted' => null,
        ]);

        $zone = ShippingZone::query()->create([
            'seller_id' => $vendor->id,
            'name' => 'Free Shipping Zone',
            'status' => true,
        ]);

        ShippingZoneLocation::query()->create([
            'shipping_zone_id' => $zone->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);

        ShippingMethod::query()->create([
            'shipping_zone_id' => $zone->id,
            'name' => 'Free Shipping',
            'method_type' => 'free_shipping',
            'free_shipping_min_amount' => 10000,
            'status' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $belowMinimum = collect(
            $this->getJson('/api/v1/shipping/quote?for_cart=1&country_id='.$country->id.'&state_id='.$state->id)
                ->assertOk()
                ->json('data.sellers.0.methods')
        );

        $this->assertNull($belowMinimum->firstWhere('name', 'Free Shipping'));

        $cartItemId = (int) Cart::query()->where('user_id', $buyer->id)->firstOrFail()->items()->latest('id')->value('id');
        $product->update(['price' => 15000, 'price_discounted' => null]);

        $this->deleteJson('/api/v1/cart/items/'.$cartItemId)->assertOk();

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $aboveMinimum = collect(
            $this->getJson('/api/v1/shipping/quote?for_cart=1&country_id='.$country->id.'&state_id='.$state->id)
                ->assertOk()
                ->json('data.sellers.0.methods')
        );

        $freeShipping = $aboveMinimum->firstWhere('name', 'Free Shipping');
        $this->assertNotNull($freeShipping);
        $this->assertSame(0, $freeShipping['flat_rate']);
    }

    public function test_apply_shipping_recalculates_dynamic_cost_at_selection_time(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $country = Country::query()->where('code', 'NG')->firstOrFail();
        $state = State::query()->where('name', 'Lagos')->firstOrFail();

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $product->update([
            'vendor_id' => $vendor->id,
            'price' => 50000,
            'price_discounted' => null,
            'shipping_dimensions' => ['weight' => 0.5],
        ]);

        $zone = ShippingZone::query()->create([
            'seller_id' => $vendor->id,
            'name' => 'Apply Quote Zone',
            'status' => true,
        ]);

        ShippingZoneLocation::query()->create([
            'shipping_zone_id' => $zone->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ]);

        $method = ShippingMethod::query()->create([
            'shipping_zone_id' => $zone->id,
            'name' => 'Per Item Rate',
            'method_type' => 'flat_rate',
            'cost_calculation_type' => 'per_item',
            'shipping_flat_cost' => 400,
            'status' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertCreated();

        $this->postJson('/api/v1/cart/shipping', [
            'shipping_method_id' => $method->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ])->assertOk()
            ->assertJsonPath('data.totals.shipping_cost', 1200);
    }
}
