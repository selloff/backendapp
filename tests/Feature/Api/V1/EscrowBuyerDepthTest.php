<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use Tests\TestCase;

class EscrowBuyerDepthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_buyer_token_exposes_confirm_delivery_when_item_shipped(): void
    {
        $response = $this->getJson('/api/v1/escrow/token/demo-buyer-escrow-deliver-token')
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'buyer')
            ->assertJsonPath('data.seller_shipped_item', true)
            ->assertJsonCount(11, 'data.stages');

        $this->assertNotEmpty($response->json('data.product.image_url'));

        $actions = $response->json('data.allowed_actions');
        $this->assertContains('confirm_delivery', $actions);
        $this->assertContains('dispute', $actions);
    }

    public function test_seller_token_exposes_confirm_shipped_after_payment(): void
    {
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
        $this->assertContains('dispute', $actions);
        $this->assertContains('confirm_shipped', $actions);
    }

    public function test_buyer_can_confirm_delivery_and_seller_is_notified(): void
    {
        $response = $this->postJson('/api/v1/escrow/token/demo-buyer-escrow-deliver-token/confirm-delivery')
            ->assertOk()
            ->assertJsonPath('data.buyer_confirmed_item_delivery', true);

        $actions = $response->json('data.allowed_actions');
        $this->assertContains('dispute', $actions);
        $this->assertNotContains('confirm_delivery', $actions);
    }

    public function test_seller_can_confirm_shipped_after_payment(): void
    {
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
        $this->assertContains('dispute', $actions);
        $this->assertNotContains('confirm_shipped', $actions);
    }

    public function test_buyer_cannot_confirm_shipped(): void
    {
        $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
        $transaction->update([
            'buyer_agreed' => true,
            'seller_agreed' => true,
            'status' => 'processing',
            'payment_received' => true,
        ]);

        $this->postJson('/api/v1/escrow/token/demo-buyer-escrow-token/confirm-shipped')
            ->assertForbidden();
    }
}
