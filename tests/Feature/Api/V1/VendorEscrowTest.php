<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorEscrowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_list_their_escrow_transactions(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/v1/vendor/escrow-transactions')
            ->assertOk()
            ->assertJsonPath('success', true);

        $rows = $response->json('data.data');
        $this->assertNotEmpty($rows);
        $this->assertContains('DEMOESCROW1', array_column($rows, 'ref'));
        $this->assertNotEmpty($rows[0]['product']['image_url'] ?? null);

        $demoRow = collect($rows)->firstWhere('ref', 'DEMOESCROW1');
        $product = Product::query()->where('sku', 'DEMO-AUDIO-1')->firstOrFail();
        $this->assertEqualsWithDelta((float) $product->price, (float) $demoRow['amount'], 0.01);
    }

    public function test_vendor_can_view_escrow_transaction_as_seller(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->getJson("/api/v1/vendor/escrow-transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'seller')
            ->assertJsonPath('data.ref', 'DEMOESCROW1')
            ->assertJsonPath('data.allowed_actions', fn (array $actions) => in_array('confirm', $actions, true));
    }

    public function test_vendor_escrow_amount_normalizes_column_stored_at_100x_product_price(): void
    {
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

        $this->assertEqualsWithDelta((float) $product->price, (float) $response->json('data.amount'), 0.01);
    }

    public function test_vendor_escrow_amount_prefers_column_over_inflated_metadata_item_price(): void
    {
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

        $this->assertEqualsWithDelta((float) $product->price, (float) $response->json('data.amount'), 0.01);
    }

    public function test_buyer_cannot_access_vendor_escrow_routes(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/vendor/escrow-transactions')->assertForbidden();
        $this->getJson("/api/v1/vendor/escrow-transactions/{$transaction->id}")->assertForbidden();
    }

    public function test_vendor_cannot_view_another_sellers_escrow_transaction(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $otherSeller = User::factory()->create();
        $transaction = EscrowTransaction::query()->where('ref', 'DEMOESCROW1')->firstOrFail();
        $transaction->update(['seller_id' => $otherSeller->id]);

        Sanctum::actingAs($vendor);

        $this->getJson("/api/v1/vendor/escrow-transactions/{$transaction->id}")->assertNotFound();
    }
}
