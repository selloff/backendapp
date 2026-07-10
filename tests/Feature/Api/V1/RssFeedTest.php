<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Services\Platform\PlatformSettingsService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('latest products rss returns xml with listed product', function () {
    config(['selloff.spa_url' => 'https://shop.selloff.test']);

    $vendor = User::factory()->create(['username' => 'rss-vendor']);
    $category = Category::factory()->create(['slug' => 'rss-phones', 'status' => true]);
    $product = publishedProduct_in_RssFeed($vendor, $category, 'rss-latest-item');

    $response = $this->get('/rss/latest-products');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');

    $content = $response->getContent();
    $this->assertStringContainsString('<rss version="2.0"', $content);
    $this->assertStringContainsString('Latest Products', $content);
    $this->assertStringContainsString('Rss latest item', $content);
    $this->assertStringContainsString('https://shop.selloff.test/products/'.$product->slug, $content);
    $this->assertStringContainsString('rss-vendor', $content);
});

test('rss feeds are disabled when platform setting is off', function () {
    app(PlatformSettingsService::class)->upsertMany(['rss_enabled' => false], 'general');

    $this->get('/rss/latest-products')
        ->assertForbidden()
        ->assertSee('RSS Disabled');
});

test('category rss redirects to index when category missing', function () {
    config(['selloff.spa_url' => 'https://shop.selloff.test']);

    $this->get('/rss/category/missing-category')
        ->assertRedirect('https://shop.selloff.test/rss-feeds');
});

test('seller rss redirects when vendor opted out', function () {
    config(['selloff.spa_url' => 'https://shop.selloff.test']);

    $vendor = User::factory()->create([
        'slug' => 'private-seller',
        'username' => 'private-seller',
        'show_rss_feeds' => false,
    ]);

    $this->get('/rss/seller/'.$vendor->slug)
        ->assertRedirect('https://shop.selloff.test/shops/'.$vendor->slug);
});

test('seller rss returns products when enabled', function () {
    config(['selloff.spa_url' => 'https://shop.selloff.test']);

    $vendor = User::factory()->create([
        'slug' => 'open-seller',
        'username' => 'open-seller',
        'show_rss_feeds' => true,
    ]);
    $category = Category::factory()->create(['status' => true]);
    publishedProduct_in_RssFeed($vendor, $category, 'seller-rss-item');

    $response = $this->get('/rss/seller/open-seller');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
        ->assertSee('Seller rss item', false);
});

test('rss directory api lists parent category feeds', function () {
    config(['selloff.spa_url' => 'https://shop.selloff.test']);

    $this->getJson('/api/v1/rss/directory')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.latest_feed_url', 'https://shop.selloff.test/rss/latest-products')
        ->assertJsonPath('data.featured_feed_url', 'https://shop.selloff.test/rss/featured-products');
});

function publishedProduct_in_RssFeed(User $vendor, Category $category, string $slug): Product
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
