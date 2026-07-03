<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Wishlist;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Pass19MobileApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_paginated_by_category_slug_returns_products_for_slug(): void
    {
        $category = Category::query()->where('slug', 'smartphones')->firstOrFail();

        $this->assertGreaterThan(0, Product::query()->where('category_id', $category->id)->count());

        $response = $this->getJson('/api/v1/products/paginated-by-category-slug?slug=smartphones')
            ->assertOk()
            ->assertJsonPath('status', '1');

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_paginated_by_declutter_returns_discounted_products(): void
    {
        $this->assertGreaterThan(
            0,
            Product::query()
                ->whereNotNull('price_discounted')
                ->whereColumn('price_discounted', '<', 'price')
                ->count(),
        );

        $response = $this->getJson('/api/v1/products/paginated-by-declutter')
            ->assertOk()
            ->assertJsonPath('status', '1');

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_paginated_by_freebies_returns_zero_price_products(): void
    {
        $this->assertGreaterThan(0, Product::query()->where('price', 0)->count());

        $response = $this->getJson('/api/v1/products/paginated-by-freebies')
            ->assertOk()
            ->assertJsonPath('status', '1');

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_paginated_by_favourite_listings_returns_wishlist_products(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->assertGreaterThan(0, Wishlist::query()->where('user_id', $buyer->id)->count());

        $response = $this->getJson('/api/v1/products/paginated-by-fovourite-listings')
            ->assertOk()
            ->assertJsonPath('status', '1');

        $this->assertNotEmpty($response->json('data'));
    }

    public function test_update_product_category_requires_admin_and_bulk_reassigns(): void
    {
        $phones = Category::query()->where('slug', 'smartphones')->firstOrFail();
        $accessories = Category::query()->where('slug', 'accessories')->firstOrFail();

        $product = Product::query()
            ->where('sku', 'DEMO-FREEBIE-1')
            ->where('category_id', $phones->id)
            ->firstOrFail();

        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/update-product-category', [
            'old_category' => $phones->id,
            'new_category' => $accessories->id,
        ])->assertForbidden();

        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/update-product-category', [
            'old_category' => $phones->id,
            'new_category' => $accessories->id,
        ])
            ->assertOk()
            ->assertJsonPath('status', '1')
            ->assertJsonPath('data.updated_count', fn ($count) => $count >= 1);

        $product->refresh();
        $this->assertSame($accessories->id, $product->category_id);

        // Restore for other tests
        $product->update(['category_id' => $phones->id]);
    }
}
