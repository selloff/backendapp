<?php

use App\Models\User;
use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Modules\Selloff\Shipping\Models\ShippingZone;
use App\Modules\Selloff\Shipping\Models\ShippingZoneLocation;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('cart quote uses weight tiers for flat rate methods', function () {
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

    expect($weightMethod)->not->toBeNull();
    expect($weightMethod['flat_rate'])->toBe(1200);
});

test('free shipping only appears when minimum order is met', function () {
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

    expect($belowMinimum->firstWhere('name', 'Free Shipping'))->toBeNull();

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
    expect($freeShipping)->not->toBeNull();
    expect($freeShipping['flat_rate'])->toBe(0);
});

test('apply shipping recalculates dynamic cost at selection time', function () {
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
});
