<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorProductListParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_items_for_sale_only_includes_published_visible_products(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/products')
            ->assertOk()
            ->assertJsonMissing(['data' => [['sku' => 'DEMO-PENDING-1']]])
            ->assertJsonMissing(['data' => [['sku' => 'DEMO-FREEBIE-1']]]);

        $this->getJson('/api/v1/vendor/products?st=pending')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'DEMO-PENDING-1']);
    }

    public function test_vendor_pending_list_matches_legacy_status_filter(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

        Product::query()->create([
            'vendor_id' => $vendor->id,
            'sku' => 'PARITY-HIDDEN-PENDING',
            'slug' => 'parity-hidden-pending',
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'status' => 'hidden',
            'visibility' => 'hidden',
            'is_active' => false,
            'is_verified' => false,
            'is_draft' => false,
            'is_deleted' => false,
            'price' => 1000,
            'currency_code' => 'NGN',
            'stock' => 1,
        ]);

        Product::query()->create([
            'vendor_id' => $vendor->id,
            'sku' => 'PARITY-UNVERIFIED-PUBLISHED',
            'slug' => 'parity-unverified-published',
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'status' => 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'is_verified' => false,
            'is_draft' => false,
            'is_deleted' => false,
            'price' => 1000,
            'currency_code' => 'NGN',
            'stock' => 1,
        ]);

        Sanctum::actingAs($vendor);

        $pendingSkus = collect($this->getJson('/api/v1/vendor/products?st=pending')->json('data.data'))
            ->pluck('sku')
            ->all();

        $this->assertContains('DEMO-PENDING-1', $pendingSkus);
        $this->assertNotContains('PARITY-HIDDEN-PENDING', $pendingSkus);
        $this->assertNotContains('PARITY-UNVERIFIED-PUBLISHED', $pendingSkus);

        $activeSkus = collect($this->getJson('/api/v1/vendor/products')->json('data.data'))
            ->pluck('sku')
            ->all();

        $this->assertContains('PARITY-UNVERIFIED-PUBLISHED', $activeSkus);
        $this->assertNotContains('DEMO-PENDING-1', $activeSkus);
        $this->assertNotContains('PARITY-HIDDEN-PENDING', $activeSkus);
    }

    public function test_affiliate_product_appears_in_items_for_sale_when_published(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

        Product::query()->create([
            'vendor_id' => $vendor->id,
            'sku' => 'PARITY-AFFILIATE-ACTIVE',
            'slug' => 'parity-affiliate-active',
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'status' => 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'is_verified' => false,
            'is_affiliate' => true,
            'is_draft' => false,
            'is_deleted' => false,
            'price' => 1000,
            'currency_code' => 'NGN',
            'stock' => 5,
        ]);

        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/products')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'PARITY-AFFILIATE-ACTIVE', 'is_affiliate' => true]);
    }

    public function test_legacy_numeric_status_and_visibility_still_match_items_for_sale(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

        Product::query()->create([
            'vendor_id' => $vendor->id,
            'sku' => 'PARITY-LEGACY-STATUS-1',
            'slug' => 'parity-legacy-status-1',
            'type' => 'physical',
            'listing_type' => 'sell_on_site',
            'status' => '1',
            'visibility' => '1',
            'is_active' => true,
            'is_affiliate' => true,
            'is_commission_set' => true,
            'commission_rate' => 8,
            'is_draft' => false,
            'is_deleted' => false,
            'price' => 120000,
            'currency_code' => 'NGN',
            'stock' => 10,
        ]);

        ProductTranslation::query()->create([
            'product_id' => Product::query()->where('sku', 'PARITY-LEGACY-STATUS-1')->value('id'),
            'locale' => 'en',
            'title' => 'Top Cartier sunglasses (Unisex) Foreign used',
        ]);

        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/products')
            ->assertOk()
            ->assertJsonFragment([
                'sku' => 'PARITY-LEGACY-STATUS-1',
                'is_affiliate' => true,
            ]);
    }
}
