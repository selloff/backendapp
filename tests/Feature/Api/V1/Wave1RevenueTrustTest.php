<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Wave1RevenueTrustTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_add_to_cart_returns_add_to_cart_gtm_event(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.gtm_events.0.event', 'add_to_cart')
            ->assertJsonPath('data.gtm_events.0.eventData.item_id', (string) $product->id);
    }

    public function test_guest_cart_merge_moves_items_to_user_cart(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();

        $guestResponse = $this->postJson('/api/v1/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $guestToken = $guestResponse->json('data.guest_token');

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/merge-guest', ['guest_token' => $guestToken])
            ->assertOk()
            ->assertJsonPath('data.merged_items', 1)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_add_to_cart_rejects_classified_listing(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_guest_add_to_cart_rejects_classified_listing(): void
    {
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        $this->postJson('/api/v1/guest/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_initiate_escrow_returns_buy_with_escrow_gtm_event(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/initiate-escrow', ['product_id' => $product->id])
            ->assertCreated()
            ->assertJsonPath('data.gtm_events.0.event', 'buy_with_escrow')
            ->assertJsonPath('data.gtm_events.0.eventData.item_id', (string) $product->id);
    }

    public function test_cart_shipping_quote_groups_methods_by_seller(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->getJson('/api/v1/shipping/quote?for_cart=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'sellers' => [
                        ['seller_id', 'seller', 'methods'],
                    ],
                    'has_multiple_sellers',
                ],
            ]);
    }
}
