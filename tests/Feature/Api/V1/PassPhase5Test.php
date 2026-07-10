<?php

use App\Models\User;
use App\Modules\Selloff\Cart\Models\CartItem;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('cart item can be updated and removed', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
    $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertCreated();

    $item = CartItem::query()->whereHas('cart', fn ($q) => $q->where('user_id', $buyer->id))->firstOrFail();

    $this->patchJson("/api/v1/cart/items/{$item->id}", ['quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', 3);

    $this->deleteJson("/api/v1/cart/items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('data.items', []);
});

test('payment methods expose gateway logos', function () {
    $response = $this->getJson('/api/v1/payments/methods')->assertOk();
    $bankTransfer = collect($response->json('data.methods'))->firstWhere('key', 'bank_transfer');

    expect($bankTransfer['logos'] ?? null)->toBe(['bank']);
});

test('service payment completion returns membership payload', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $plan = MembershipPlan::query()->where('is_active', true)->firstOrFail();
    $transaction = MembershipTransaction::query()->create([
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'amount' => $plan->price,
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
    ]);

    $this->getJson('/api/v1/service-payments/completion?service_type=membership&transaction_id='.$transaction->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.service_type', 'membership')
        ->assertJsonPath('data.is_pending', true)
        ->assertJsonPath('data.plan.id', $plan->id);
});

test('service payment completion returns wallet deposit payload', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $deposit = WalletDeposit::query()->create([
        'user_id' => $buyer->id,
        'amount' => 5000,
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
        'transaction_id' => 'WD-PHASE5-001',
    ]);

    $this->getJson('/api/v1/service-payments/completion?service_type=add_funds&transaction_id='.$deposit->id)
        ->assertOk()
        ->assertJsonPath('data.service_type', 'add_funds')
        ->assertJsonPath('data.transaction_number', 'WD-PHASE5-001');
});

test('service payment completion returns promotion payload', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $product = Product::query()->where('vendor_id', $vendor->id)->where('status', 'published')->firstOrFail();
    $transaction = PromotionTransaction::query()->create([
        'user_id' => $vendor->id,
        'product_id' => $product->id,
        'amount' => 2500,
        'currency_code' => 'NGN',
        'status' => 'completed',
    ]);

    $this->getJson('/api/v1/service-payments/completion?service_type=promote&transaction_id='.$transaction->id)
        ->assertOk()
        ->assertJsonPath('data.service_type', 'promote')
        ->assertJsonPath('data.product.id', $product->id);
});
