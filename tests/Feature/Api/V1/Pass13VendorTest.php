<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor dashboard endpoints are available', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/products')->assertOk()->assertJsonPath('success', true);
    $this->getJson('/api/v1/vendor/orders')->assertOk();
    $this->getJson('/api/v1/vendor/coupons')->assertOk();
    $this->getJson('/api/v1/vendor/reviews')->assertOk();
    $this->getJson('/api/v1/vendor/refunds')->assertOk();
    $this->getJson('/api/v1/vendor/shipping/zones')->assertOk();

    $createdProduct = $this->postJson('/api/v1/products', [
        'title' => 'Vendor Test Item',
        'price' => 4500,
        'stock' => 5,
    ])->assertCreated()->json('data');

    $this->putJson('/api/v1/products/'.$createdProduct['id'], [
        'price' => 5000,
    ])->assertOk();

    $this->getJson('/api/v1/vendor/products')->assertOk();

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);
    $this->postJson('/api/v1/cart/items', ['product_id' => $createdProduct['id'], 'quantity' => 1])->assertCreated();
    $checkout = $this->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');
    $placed = $this->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
        ->assertCreated()
        ->json('data');

    Sanctum::actingAs($vendor);
    $this->getJson('/api/v1/vendor/orders/'.$placed['id'])
        ->assertOk()
        ->assertJsonPath('data.id', $placed['id']);

    $this->postJson('/api/v1/vendor/shipping/zones', ['name' => 'Lagos zone'])
        ->assertCreated();

    $this->postJson('/api/v1/vendor/coupons', [
        'coupon_code' => 'VENDOR10',
        'discount_rate' => 10,
    ])->assertCreated();
});
