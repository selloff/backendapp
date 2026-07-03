<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WaveAPaymentScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_cart_payment_methods_exclude_cod_and_stripe(): void
    {
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

        $this->assertContains('wallet_balance', $keys);
        $this->assertContains('bank_transfer', $keys);
        $this->assertNotContains('cash_on_delivery', $keys);
        $this->assertNotContains('stripe', $keys);
    }
}
