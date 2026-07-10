<?php

use App\Models\User;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('cart payment methods exclude cod and stripe', function () {
    app(PaymentGatewaySettingsService::class)->update([
        'wallet_enabled' => true,
        'bank_transfer_enabled' => true,
        'cash_on_delivery_enabled' => true,
        'stripe_enabled' => true,
        'stripe_public_key' => 'pk_test_wave_a',
    ]);

    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $keys = collect($this->getJson('/api/v1/payments/methods?context=cart')
        ->assertOk()
        ->json('data.methods'))
        ->pluck('key')
        ->all();

    expect($keys)->toContain('wallet_balance');
    expect($keys)->toContain('bank_transfer');
    expect($keys)->not->toContain('cash_on_delivery');
    expect($keys)->not->toContain('stripe');
});
