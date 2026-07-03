<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\User\Models\ShippingAddress;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassPhase6Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_shipping_address_supports_full_fields_and_default(): void
    {
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
    }

    public function test_order_detail_exposes_product_type_and_digital_downloads(): void
    {
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
    }

    public function test_buyer_can_fetch_invoice_for_paid_order(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $order = Order::query()->where('buyer_id', $buyer->id)->where('payment_status', 'payment_received')->firstOrFail();

        $this->getJson("/api/v1/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertJsonPath('data.invoice_number', 'INV-'.$order->order_number)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    public function test_wallet_summary_includes_expenses(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/wallet')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['balance', 'transactions', 'expenses', 'deposits'],
            ]);
    }

    public function test_account_downloads_lists_digital_sales(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->assertTrue(
            DigitalSale::query()->where('buyer_id', $buyer->id)->exists(),
            'Demo seeder should create at least one digital sale.',
        );

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
    }
}
