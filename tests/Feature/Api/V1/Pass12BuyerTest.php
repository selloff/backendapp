<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('buyer can manage wishlist shipping addresses wallet and refunds', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('success', true);

    \App\Modules\Selloff\Catalog\Models\Wishlist::query()->where('user_id', $buyer->id)->delete();

    $this->postJson('/api/v1/wishlist/'.$product->id)
        ->assertCreated()
        ->assertJsonPath('data.product_id', $product->id);

    $this->getJson('/api/v1/wishlist')
        ->assertOk()
        ->assertJsonCount(1, 'data.items');

    $this->deleteJson('/api/v1/wishlist/'.$product->id)->assertOk();

    $address = $this->postJson('/api/v1/profile/shipping-addresses', [
        'title' => 'Home',
        'address' => '12 Demo Street',
        'is_default' => true,
    ])->assertCreated()->json('data');

    $this->assertDatabaseHas('shipping_addresses', [
        'id' => $address['id'],
        'user_id' => $buyer->id,
        'title' => 'Home',
    ]);

    $this->getJson('/api/v1/profile/shipping-addresses')
        ->assertOk()
        ->assertJsonPath('data.addresses.0.title', 'Home');

    $this->getJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonStructure(['data' => ['balance', 'transactions', 'deposits']]);

    $this->postJson('/api/v1/wallet/deposits', [
        'amount' => 1000,
        'payment_method' => 'bank_transfer',
    ])->assertCreated();

    Sanctum::actingAs($buyer);
    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();
    $checkout = $this->postJson('/api/v1/checkout', ['payment_method' => 'wallet_balance'])->assertCreated()->json('data');
    $order = $this->postJson('/api/v1/checkout/wallet', ['checkout_token' => $checkout['checkout_token']])
        ->assertCreated()
        ->json('data');

    $this->postJson('/api/v1/orders/'.$order['id'].'/refund-requests', [
        'description' => 'Changed my mind',
    ])->assertCreated();

    $this->getJson('/api/v1/refund-requests')
        ->assertOk()
        ->assertJsonPath('data.data.0.order_id', $order['id']);

    $this->getJson('/api/v1/vendors/demo-electronics')
        ->assertOk()
        ->assertJsonStructure(['data' => ['vendor', 'products']]);
});
