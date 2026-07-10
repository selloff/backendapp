<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('public catalog endpoints list demo products', function () {
    $this->getJson('/api/v1/products')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['data', 'total']]);

    $this->getJson('/api/v1/categories?roots_only=1')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->getJson('/api/v1/vendors/demo-electronics')
        ->assertOk()
        ->assertJsonPath('data.vendor.shop_name', 'Demo Electronics');
});

test('buyer can complete wallet purchase flow', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $startingBalance = (float) $buyer->wallet_balance;

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.totals.is_valid', true);

    $this->postJson('/api/v1/cart/coupon', [
        'coupon_code' => 'DEMO10',
    ])->assertOk()
        ->assertJsonPath('data.coupon_code', 'DEMO10');

    $cart = $this->getJson('/api/v1/cart')->assertOk()->json('data');
    expect($cart['totals']['discount_amount'])->toBeGreaterThan(0);

    $checkout = $this->postJson('/api/v1/checkout', [
        'payment_method' => 'wallet_balance',
    ])->assertCreated()->json('data');

    $orderResponse = $this->postJson('/api/v1/checkout/wallet', [
        'checkout_token' => $checkout['checkout_token'],
    ]);

    $orderResponse->assertCreated()
        ->assertJsonPath('data.payment_method', 'wallet_balance')
        ->assertJsonPath('data.payment_status', 'payment_received');

    $orderTotal = (float) $orderResponse->json('data.price_total');
    $buyer->refresh();

    expect((float) $buyer->wallet_balance)->toBe(round($startingBalance - $orderTotal, 2));
    expect(Order::query()->where('buyer_id', $buyer->id)->count())->toBe(3);
    expect($product->fresh()->stock)->toBe(23);

    $this->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonPath('data.total', 3);

    $this->postJson('/api/v1/wishlist/'.$product->id)
        ->assertCreated();

    $this->getJson('/api/v1/wishlist')
        ->assertOk()
        ->assertJsonPath('data.items.0.product.id', $product->id);
});
