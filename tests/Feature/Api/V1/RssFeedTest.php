<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Services\Platform\PlatformSettingsService;
use Tests\TestCase;

class RssFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_latest_products_rss_returns_xml_with_listed_product(): void
    {
        config(['selloff.spa_url' => 'https://shop.selloff.test']);

        $vendor = User::factory()->create(['username' => 'rss-vendor']);
        $category = Category::factory()->create(['slug' => 'rss-phones', 'status' => true]);
        $product = $this->publishedProduct($vendor, $category, 'rss-latest-item');

        $response = $this->get('/rss/latest-products');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');

        $content = $response->getContent();
        $this->assertStringContainsString('<rss version="2.0"', $content);
        $this->assertStringContainsString('Latest Products', $content);
        $this->assertStringContainsString('Rss latest item', $content);
        $this->assertStringContainsString('https://shop.selloff.test/products/'.$product->slug, $content);
        $this->assertStringContainsString('rss-vendor', $content);
    }

    public function test_rss_feeds_are_disabled_when_platform_setting_is_off(): void
    {
        app(PlatformSettingsService::class)->upsertMany(['rss_enabled' => false], 'general');

        $this->get('/rss/latest-products')
            ->assertForbidden()
            ->assertSee('RSS Disabled');
    }

    public function test_category_rss_redirects_to_index_when_category_missing(): void
    {
        config(['selloff.spa_url' => 'https://shop.selloff.test']);

        $this->get('/rss/category/missing-category')
            ->assertRedirect('https://shop.selloff.test/rss-feeds');
    }

    public function test_seller_rss_redirects_when_vendor_opted_out(): void
    {
        config(['selloff.spa_url' => 'https://shop.selloff.test']);

        $vendor = User::factory()->create([
            'slug' => 'private-seller',
            'username' => 'private-seller',
            'show_rss_feeds' => false,
        ]);

        $this->get('/rss/seller/'.$vendor->slug)
            ->assertRedirect('https://shop.selloff.test/shops/'.$vendor->slug);
    }

    public function test_seller_rss_returns_products_when_enabled(): void
    {
        config(['selloff.spa_url' => 'https://shop.selloff.test']);

        $vendor = User::factory()->create([
            'slug' => 'open-seller',
            'username' => 'open-seller',
            'show_rss_feeds' => true,
        ]);
        $category = Category::factory()->create(['status' => true]);
        $this->publishedProduct($vendor, $category, 'seller-rss-item');

        $response = $this->get('/rss/seller/open-seller');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
            ->assertSee('Seller rss item', false);
    }

    public function test_rss_directory_api_lists_parent_category_feeds(): void
    {
        config(['selloff.spa_url' => 'https://shop.selloff.test']);

        $this->getJson('/api/v1/rss/directory')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.latest_feed_url', 'https://shop.selloff.test/rss/latest-products')
            ->assertJsonPath('data.featured_feed_url', 'https://shop.selloff.test/rss/featured-products');
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
