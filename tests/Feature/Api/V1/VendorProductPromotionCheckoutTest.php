<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('promotion with insufficient wallet returns validation error', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $vendor->update(['wallet_balance' => 0]);
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/promote", [
        'plan_type' => 'daily',
        'duration' => 1,
        'payment_method' => 'wallet_balance',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['wallet_balance']);
});

test('promotion with bank transfer creates pending transaction without promoting product', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $vendor->update(['wallet_balance' => 0]);
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
    $product->update(['is_promoted' => false, 'promoted_until' => null]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/promote", [
        'plan_type' => 'daily',
        'duration' => 2,
        'payment_method' => 'bank_transfer',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payment_method', 'bank_transfer');

    $product->refresh();
    expect($product->is_promoted)->toBeFalse();
});

test('promotion paystack completion promotes product', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $vendor->update(['wallet_balance' => 0]);
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
    $product->update(['is_promoted' => false, 'promoted_until' => null]);

    Sanctum::actingAs($vendor);

    app(PaymentGatewaySettingsService::class)->updateLegacyGateway('paystack', [
        'status' => true,
        'public_key' => 'pk_test_demo',
        'secret_key' => 'sk_test_demo',
    ]);

    $response = $this->postJson("/api/v1/vendor/products/{$product->id}/promote", [
        'plan_type' => 'daily',
        'duration' => 1,
        'payment_method' => 'paystack',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.requires_action', true)
        ->assertJsonPath('data.action.type', 'paystack_inline');

    $transactionId = (int) $response->json('data.id');
    $reference = (string) $response->json('data.action.reference');

    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => (int) round(((float) $response->json('data.amount')) * 100),
                'currency' => 'NGN',
                'reference' => $reference,
            ],
        ]),
    ]);

    $this->postJson("/api/v1/vendor/promotion-transactions/{$transactionId}/paystack/complete", [
        'payment_reference' => $reference,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $product->refresh();
    expect($product->is_promoted)->toBeTrue();
    expect($product->promoted_until)->not->toBeNull();
});
