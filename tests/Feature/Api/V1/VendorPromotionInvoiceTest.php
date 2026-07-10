<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor promotion invoice includes pending payment details', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

    $transaction = PromotionTransaction::query()->create([
        'user_id' => $vendor->id,
        'product_id' => $product->id,
        'payment_method' => 'bank_transfer',
        'amount' => 2500,
        'currency_code' => 'NGN',
        'status' => 'pending',
        'purchased_plan' => 'Daily plan (3 days)',
        'day_count' => 3,
        'metadata' => [
            'plan_type' => 'daily',
            'duration' => 3,
            'purchased_plan' => 'Daily plan (3 days)',
            'day_count' => 3,
            'expires_at' => now()->addDays(3)->toIso8601String(),
        ],
    ]);

    Sanctum::actingAs($vendor);

    $this->getJson("/api/v1/vendor/promotion-transactions/{$transaction->id}/invoice")
        ->assertOk()
        ->assertJsonPath('data.invoice_number', 'INVP'.$transaction->id)
        ->assertJsonPath('data.is_pending_payment', true)
        ->assertJsonPath('data.can_complete_payment', true)
        ->assertJsonPath('data.purchased_plan', 'Daily plan (3 days)')
        ->assertJsonPath('data.product.id', $product->id)
        ->assertJsonStructure(['data' => ['company', 'client', 'payment', 'items', 'totals']]);
});

test('vendor can resume pending paystack promotion from invoice flow', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $vendor->update(['wallet_balance' => 0]);
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

    app(PaymentGatewaySettingsService::class)->updateLegacyGateway('paystack', [
        'status' => true,
        'public_key' => 'pk_test_demo',
        'secret_key' => 'sk_test_demo',
    ]);

    $transaction = PromotionTransaction::query()->create([
        'user_id' => $vendor->id,
        'product_id' => $product->id,
        'payment_method' => 'paystack',
        'payment_reference' => 'PRM-RESUME1234',
        'amount' => 1000,
        'currency_code' => 'NGN',
        'status' => 'pending',
        'purchased_plan' => 'Daily plan (1 days)',
        'day_count' => 1,
        'metadata' => [
            'plan_type' => 'daily',
            'duration' => 1,
            'purchased_plan' => 'Daily plan (1 days)',
            'day_count' => 1,
            'expires_at' => now()->addDay()->toIso8601String(),
        ],
    ]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/promotion-transactions/{$transaction->id}/resume-payment")
        ->assertOk()
        ->assertJsonPath('data.requires_action', true)
        ->assertJsonPath('data.action.type', 'paystack_inline')
        ->assertJsonPath('data.action.reference', 'PRM-RESUME1234');

    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => 100000,
                'currency' => 'NGN',
                'reference' => 'PRM-RESUME1234',
            ],
        ]),
    ]);

    $this->postJson("/api/v1/vendor/promotion-transactions/{$transaction->id}/paystack/complete", [
        'payment_reference' => 'PRM-RESUME1234',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $product->refresh();
    expect($product->is_promoted)->toBeTrue();
});
