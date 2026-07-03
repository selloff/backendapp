<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TopAdPromotionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_fetch_top_ad_pricing_options(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/top-ad-pricing')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'options' => [
                        ['duration_days', 'price', 'rank_weight', 'label'],
                    ],
                    'badge_label',
                    'stack_weight_bonus',
                ],
            ])
            ->assertJsonCount(4, 'data.options');
    }

    public function test_wallet_purchase_applies_stacked_top_boost_to_product(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('vendor_id', $vendor->id)->where('is_draft', false)->firstOrFail();
        $vendor->update(['wallet_balance' => 50000]);

        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$product->id}/purchase-top-ad", [
            'duration_days' => 7,
            'payment_method' => 'wallet_balance',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed');

        $product->refresh();
        $this->assertTrue($product->top_boost_active);
        $this->assertSame(1, (int) $product->top_boost_stack_count);
        $this->assertSame(100, (int) $product->top_boost_weight);
        $this->assertNotNull($product->top_boost_expires_at);

        $this->postJson("/api/v1/vendor/products/{$product->id}/purchase-top-ad", [
            'duration_days' => 14,
            'payment_method' => 'wallet_balance',
        ])->assertCreated();

        $product->refresh();
        $this->assertSame(2, (int) $product->top_boost_stack_count);
        $this->assertGreaterThan(100, (int) $product->top_boost_weight);
    }

    public function test_recommended_sort_ranks_higher_top_ad_stack_above_peer(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $peer = User::query()->where('email', 'vendor2@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $timestamp = now()->subHours(2);

        $boosted = $this->publishedProduct($vendor, $category, 'top-ad-rank-boosted');
        $regular = $this->publishedProduct($peer, $category, 'top-ad-rank-regular');

        Product::query()->whereIn('id', [$boosted->id, $regular->id])->update([
            'created_at' => $timestamp,
            'is_promoted' => false,
        ]);

        $vendor->update(['wallet_balance' => 50000]);
        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$boosted->id}/purchase-top-ad", [
            'duration_days' => 30,
            'payment_method' => 'wallet_balance',
        ])->assertCreated();

        $this->postJson("/api/v1/vendor/products/{$boosted->id}/purchase-top-ad", [
            'duration_days' => 7,
            'payment_method' => 'wallet_balance',
        ])->assertCreated();

        $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($boosted->id));
        $this->assertTrue($ids->contains($regular->id));
        $this->assertTrue($ids->search($boosted->id) < $ids->search($regular->id));
    }

    public function test_top_ad_transaction_metadata_is_tagged(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('vendor_id', $vendor->id)->where('is_draft', false)->firstOrFail();
        $vendor->update(['wallet_balance' => 50000]);

        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$product->id}/purchase-top-ad", [
            'duration_days' => 7,
            'payment_method' => 'wallet_balance',
        ])->assertCreated();

        $tx = PromotionTransaction::query()->where('product_id', $product->id)->latest('id')->firstOrFail();
        $this->assertSame('top_ad', $tx->metadata['kind'] ?? null);
        $this->assertSame(7, (int) ($tx->metadata['duration_days'] ?? 0));
    }

    private function publishedProduct(User $vendor, Category $category, string $slug): Product
    {
        $product = Product::query()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'slug' => $slug,
            'price' => 5000,
            'status' => 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'is_draft' => false,
            'is_deleted' => false,
            'currency_code' => 'NGN',
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'title' => ucfirst(str_replace('-', ' ', $slug)),
        ]);

        return $product;
    }
}
