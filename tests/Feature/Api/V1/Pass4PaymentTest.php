<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('payment methods include wallet and bank transfer for cart', function () {
    $response = $this->getJson('/api/v1/payments/methods')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['key' => 'wallet_balance'])
        ->assertJsonFragment(['key' => 'bank_transfer']);

    $cartKeys = collect($response->json('data.methods'))->pluck('key');
    expect($cartKeys)->not->toContain('stripe');
});

test('service payment methods can include stripe', function () {
    $response = $this->getJson('/api/v1/payments/methods?context=service')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['key' => 'stripe']);

    $stripe = collect($response->json('data.methods'))->firstWhere('key', 'stripe');
    expect($stripe['logos'] ?? null)->toBe(['visa', 'mastercard', 'stripe']);
});

test('cart checkout rejects stripe payment method', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertCreated();

    $this->postJson('/api/v1/checkout', ['payment_method' => 'stripe'])
        ->assertStatus(422);
});

test('bank transfer flow requires admin approval before stock is finalized', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-CASE-1')->firstOrFail();
    $startingStock = (int) $product->stock;

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertCreated();

    $checkout = $this->postJson('/api/v1/checkout', ['payment_method' => 'bank_transfer'])
        ->assertCreated()
        ->json('data');

    $transfer = $this->postJson('/api/v1/checkout/bank-transfer', [
        'checkout_token' => $checkout['checkout_token'],
        'payment_note' => 'Paid ref 12345',
    ])->assertCreated()
        ->json('data.bank_transfer_request');

    expect($product->fresh()->stock)->toBe($startingStock);

    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/payments/bank-transfers/'.$transfer['id'].'/approve')
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'payment_received');

    expect($product->fresh()->stock)->toBe($startingStock - 1);
    expect(BankTransferRequest::query()->find($transfer['id'])?->status)->toBe('approved');
});

test('wallet demo deposit and shipping quote', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $country = Country::query()->where('code', 'NG')->firstOrFail();
    $state = State::query()->where('name', 'Lagos')->firstOrFail();
    $method = ShippingMethod::query()->where('name', 'Standard Delivery')->firstOrFail();
    $startingBalance = (float) $buyer->wallet_balance;

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/wallet/deposits', [
        'amount' => 2500,
        'payment_method' => 'demo',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'completed');

    $buyer->refresh();
    expect((float) $buyer->wallet_balance)->toBe(round($startingBalance + 2500, 2));

    $this->getJson('/api/v1/shipping/quote?country_id='.$country->id.'&state_id='.$state->id)
        ->assertOk()
        ->assertJsonPath('data.methods.0.name', 'Standard Delivery');

    $this->postJson('/api/v1/cart/shipping', [
        'shipping_method_id' => $method->id,
        'country_id' => $country->id,
        'state_id' => $state->id,
    ])->assertOk()
        ->assertJsonPath('data.totals.shipping_cost', 1500);
});
