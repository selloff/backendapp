<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('buyer can initiate escrow for classified product', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    Sanctum::actingAs($buyer);

    $response = $this->postJson('/api/v1/initiate-escrow', ['product_id' => $product->id])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'ref',
                'stages',
                'agreement_urls' => ['buyer', 'seller'],
            ],
        ]);

    expect($response->json('data.stages'))->toHaveCount(11);
    $this->assertStringContainsString('/escrow/', (string) $response->json('data.agreement_urls.buyer'));
});

test('initiate escrow rejects duplicate open transaction', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/initiate-escrow', ['product_id' => $product->id])->assertCreated();
    $this->postJson('/api/v1/initiate-escrow', ['product_id' => $product->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['product_id']);
});

test('initiate escrow rejects sell on site product', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

    expect($product->listing_type)->toBe('sell_on_site');

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/initiate-escrow', ['product_id' => $product->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['product_id']);
});

test('initiate escrow rejects license key product', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-PHONE-2')->firstOrFail();
    $product->update(['listing_type' => 'license_key']);

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/initiate-escrow', ['product_id' => $product->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['product_id']);
});

test('escrow token confirm moves to processing when both agree', function () {
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update(['buyer_agreed' => false, 'seller_agreed' => false, 'status' => 'pending']);

    $this->postJson('/api/v1/escrow/token/demo-buyer-escrow-token/confirm')
        ->assertOk()
        ->assertJsonPath('data.buyer_agreed', true);

    $this->postJson('/api/v1/escrow/token/demo-seller-escrow-token/confirm')
        ->assertOk()
        ->assertJsonPath('data.seller_agreed', true)
        ->assertJsonPath('data.status', 'awaiting_funding')
        ->assertJsonPath('data.ui_message', 'Both parties have agreed. Selloff Escrow will contact you with next steps.');
});

test('admin can update escrow stages and complete transaction', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update([
        'buyer_agreed' => true,
        'seller_agreed' => true,
        'status' => 'awaiting_funding',
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson("/api/v1/admin/escrow/transactions/{$transaction->id}/stages", [
        'delivery_cost' => 2500,
        'delivery_address' => '12 Admiralty Way, Lekki, Lagos',
        'payment_link_sent' => true,
        'payment_link_url' => 'https://paystack.com/pay/test',
        'payment_received' => true,
        'seller_shipped_item' => true,
        'buyer_confirmed_item_delivery' => true,
        'seller_received_payment' => true,
        'transaction_complete' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.delivery_cost', '2500.00')
        ->assertJsonPath('data.transaction_complete', true)
        ->assertJsonPath('data.status', 'completed');

    $stages = $this->getJson("/api/v1/admin/escrow/transactions/{$transaction->id}")
        ->assertOk()
        ->json('data.stages');

    expect($stages)->toHaveCount(11);
    expect(collect($stages)->firstWhere('key', 'delivery_cost_set')['done'])->toBeTrue();
    expect(collect($stages)->firstWhere('key', 'transaction_complete')['done'])->toBeTrue();
});
