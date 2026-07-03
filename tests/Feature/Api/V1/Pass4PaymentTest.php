<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Pass4PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_payment_methods_include_wallet_and_bank_transfer_for_cart(): void
    {
        $response = $this->getJson('/api/v1/payments/methods')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['key' => 'wallet_balance'])
            ->assertJsonFragment(['key' => 'bank_transfer']);

        $cartKeys = collect($response->json('data.methods'))->pluck('key');
        $this->assertNotContains('stripe', $cartKeys);
    }

    public function test_service_payment_methods_can_include_stripe(): void
    {
        $response = $this->getJson('/api/v1/payments/methods?context=service')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['key' => 'stripe']);

        $stripe = collect($response->json('data.methods'))->firstWhere('key', 'stripe');
        $this->assertSame(['visa', 'mastercard', 'stripe'], $stripe['logos'] ?? null);
    }

    public function test_cart_checkout_rejects_stripe_payment_method(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertCreated();

        $this->postJson('/api/v1/checkout', ['payment_method' => 'stripe'])
            ->assertStatus(422);
    }

    public function test_bank_transfer_flow_requires_admin_approval_before_stock_is_finalized(): void
    {
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

        $this->assertSame($startingStock, $product->fresh()->stock);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/payments/bank-transfers/'.$transfer['id'].'/approve')
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'payment_received');

        $this->assertSame($startingStock - 1, $product->fresh()->stock);
        $this->assertSame('approved', BankTransferRequest::query()->find($transfer['id'])?->status);
    }

    public function test_wallet_demo_deposit_and_shipping_quote(): void
    {
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
        $this->assertSame(round($startingBalance + 2500, 2), (float) $buyer->wallet_balance);

        $this->getJson('/api/v1/shipping/quote?country_id='.$country->id.'&state_id='.$state->id)
            ->assertOk()
            ->assertJsonPath('data.methods.0.name', 'Standard Delivery');

        $this->postJson('/api/v1/cart/shipping', [
            'shipping_method_id' => $method->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
        ])->assertOk()
            ->assertJsonPath('data.totals.shipping_cost', 1500);
    }
}
