<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor can list their escrow transactions', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/v1/vendor/escrow-transactions')
        ->assertOk()
        ->assertJsonPath('success', true);

    $rows = $response->json('data.data');
    expect($rows)->not->toBeEmpty();
    expect(array_column($rows, 'ref'))->toContain('DEMOESCROW1');
    expect($rows[0]['product']['image_url'] ?? null)->not->toBeEmpty();

    $demoRow = collect($rows)->firstWhere('ref', 'DEMOESCROW1');
    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
    expect((float) $demoRow['amount'])->toEqualWithDelta((float) $product->price, 0.01);
});

test('vendor can view escrow transaction as seller', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->getJson("/api/v1/vendor/escrow-transactions/{$transaction->id}")
        ->assertOk()
        ->assertJsonPath('data.viewer_role', 'seller')
        ->assertJsonPath('data.ref', 'DEMOESCROW1')
        ->assertJsonPath('data.allowed_actions', fn (array $actions) => in_array('confirm', $actions, true));
});

test('vendor escrow amount normalizes column stored at 100x product price', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    $transaction->update([
        'amount' => (float) $product->price * 100,
        'commission_amount' => (float) $transaction->commission_amount * 100,
        'seller_amount' => (float) $transaction->seller_amount * 100,
        'metadata' => array_merge(is_array($transaction->metadata) ? $transaction->metadata : [], [
            'item_price' => (float) $product->price * 100,
        ]),
    ]);

    Sanctum::actingAs($vendor);

    $response = $this->getJson("/api/v1/vendor/escrow-transactions/{$transaction->id}")
        ->assertOk();

    expect((float) $response->json('data.amount'))->toEqualWithDelta((float) $product->price, 0.01);
});

test('vendor escrow amount prefers column over inflated metadata item price', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

    $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
    $metadata['item_price'] = (float) $product->price * 100;
    $metadata['total_amount'] = (float) $product->price * 100;
    $transaction->update(['metadata' => $metadata]);

    Sanctum::actingAs($vendor);

    $response = $this->getJson("/api/v1/vendor/escrow-transactions/{$transaction->id}")
        ->assertOk();

    expect((float) $response->json('data.amount'))->toEqualWithDelta((float) $product->price, 0.01);
});

test('buyer cannot access vendor escrow routes', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/vendor/escrow-transactions')->assertForbidden();
    $this->getJson("/api/v1/vendor/escrow-transactions/{$transaction->id}")->assertForbidden();
});

test('vendor cannot view another sellers escrow transaction', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $otherSeller = User::factory()->create();
    $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
    $transaction->update(['seller_id' => $otherSeller->id]);

    Sanctum::actingAs($vendor);

    $this->getJson("/api/v1/vendor/escrow-transactions/{$transaction->id}")->assertNotFound();
});
