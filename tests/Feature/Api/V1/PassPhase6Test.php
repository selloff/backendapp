<?php

use App\Models\User;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\User\Models\ShippingAddress;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('shipping address supports full fields and default', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/profile/shipping-addresses', [
        'title' => 'Home',
        'first_name' => 'Demo',
        'last_name' => 'Buyer',
        'email' => 'buyer@selloff.test',
        'phone_number' => '08012345678',
        'address' => '12 Marina Road',
        'address_2' => 'Suite 4',
        'zip_code' => '101001',
        'country_id' => 1,
        'state_id' => 1,
        'city_id' => 1,
        'is_default' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Home')
        ->assertJsonPath('data.is_default', true);

    $address = ShippingAddress::query()->where('user_id', $buyer->id)->latest('id')->firstOrFail();

    $this->putJson("/api/v1/profile/shipping-addresses/{$address->id}", [
        'title' => 'Office',
        'state_id' => 2,
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Office')
        ->assertJsonPath('data.state_id', 2);
});

test('order detail exposes product type and digital downloads', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $order = Order::query()->where('buyer_id', $buyer->id)->where('payment_status', 'payment_received')->firstOrFail();

    $this->getJson("/api/v1/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'invoice_number',
                'can_cancel',
                'items' => [
                    ['product_type', 'product_slug'],
                ],
            ],
        ]);
});

test('buyer can fetch invoice for paid order', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $order = Order::query()->where('buyer_id', $buyer->id)->where('payment_status', 'payment_received')->firstOrFail();

    $this->getJson("/api/v1/orders/{$order->id}/invoice")
        ->assertOk()
        ->assertJsonPath('data.invoice_number', (string) $order->order_number)
        ->assertJsonPath('data.order_number', $order->order_number);
});

test('wallet summary includes expenses', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['balance', 'transactions', 'expenses', 'deposits'],
        ]);
});

test('account downloads lists digital sales', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    expect(DigitalSale::query()->where('buyer_id', $buyer->id)->exists())->toBeTrue('Demo seeder should create at least one digital sale.');

    $this->getJson('/api/v1/account/downloads')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    ['id', 'license_key', 'purchase_code', 'product'],
                ],
            ],
        ]);
});
