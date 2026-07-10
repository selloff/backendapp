<?php

use App\Modules\Selloff\Escrow\Models\EscrowTransaction;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('buyer token exposes confirm delivery when item shipped', function () {
    $response = $this->getJson('/api/v1/escrow/token/demo-buyer-escrow-deliver-token')
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'buyer')
        ->assertJsonPath('data.seller_shipped_item', true)
        ->assertJsonCount(11, 'data.stages');

    expect($response->json('data.product.image_url'))->not->toBeEmpty();

    $actions = $response->json('data.allowed_actions');
    expect($actions)->toContain('confirm_delivery');
    expect($actions)->toContain('dispute');
});

test('seller token exposes confirm shipped after payment', function () {
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update([
        'buyer_agreed' => true,
        'seller_agreed' => true,
        'status' => 'processing',
        'payment_received' => true,
        'seller_shipped_item' => false,
    ]);

    $response = $this->getJson('/api/v1/escrow/token/demo-seller-escrow-token')
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'seller');

    $actions = $response->json('data.allowed_actions');
    expect($actions)->toContain('dispute');
    expect($actions)->toContain('confirm_shipped');
});

test('buyer can confirm delivery and seller is notified', function () {
    $response = $this->postJson('/api/v1/escrow/token/demo-buyer-escrow-deliver-token/confirm-delivery')
        ->assertOk()
        ->assertJsonPath('data.buyer_confirmed_item_delivery', true);

    $actions = $response->json('data.allowed_actions');
    expect($actions)->toContain('dispute');
    expect($actions)->not->toContain('confirm_delivery');
});

test('seller can confirm shipped after payment', function () {
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update([
        'buyer_agreed' => true,
        'seller_agreed' => true,
        'status' => 'processing',
        'payment_received' => true,
        'seller_shipped_item' => false,
    ]);

    $response = $this->postJson('/api/v1/escrow/token/demo-seller-escrow-token/confirm-shipped')
        ->assertOk()
        ->assertJsonPath('data.seller_shipped_item', true);

    $actions = $response->json('data.allowed_actions');
    expect($actions)->toContain('dispute');
    expect($actions)->not->toContain('confirm_shipped');
});

test('buyer cannot confirm shipped', function () {
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update([
        'buyer_agreed' => true,
        'seller_agreed' => true,
        'status' => 'processing',
        'payment_received' => true,
    ]);

    $this->postJson('/api/v1/escrow/token/demo-buyer-escrow-token/confirm-shipped')
        ->assertForbidden();
});
