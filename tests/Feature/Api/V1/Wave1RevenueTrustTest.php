<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('add to cart returns add to cart gtm event', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])
        ->assertCreated()
        ->assertJsonPath('data.gtm_events.0.event', 'add_to_cart')
        ->assertJsonPath('data.gtm_events.0.eventData.item_id', (string) $product->id);
});

test('guest cart merge moves items to user cart', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    $guestResponse = $this->postJson('/api/v1/guest/cart/items', [
        'product_id' => $product->id,
        'quantity' => 2,
    ])->assertCreated();

    $guestToken = $guestResponse->json('data.guest_token');

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/merge-guest', ['guest_token' => $guestToken])
        ->assertOk()
        ->assertJsonPath('data.merged_items', 1)
        ->assertJsonPath('data.items.0.product_id', $product->id)
        ->assertJsonPath('data.items.0.quantity', 2);
});

test('add to cart rejects classified listing', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['product_id']);
});

test('guest add to cart rejects classified listing', function () {
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    $this->postJson('/api/v1/guest/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['product_id']);
});

test('initiate escrow returns buy with escrow gtm event', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/initiate-escrow', ['product_id' => $product->id])
        ->assertCreated()
        ->assertJsonPath('data.gtm_events.0.event', 'buy_with_escrow')
        ->assertJsonPath('data.gtm_events.0.eventData.item_id', (string) $product->id);
});

test('cart shipping quote groups methods by seller', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->getJson('/api/v1/shipping/quote?for_cart=1')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'sellers' => [
                    ['seller_id', 'seller', 'methods'],
                ],
                'has_multiple_sellers',
            ],
        ]);
});
