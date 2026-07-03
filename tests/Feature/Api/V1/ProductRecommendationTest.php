<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use Tests\TestCase;

class ProductRecommendationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_recommended_products_prefer_same_category_as_viewed_history(): void
    {
        $viewed = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $phonesCategoryId = Category::query()->where('slug', 'smartphones')->value('id');
        $laptopsCategoryId = Category::query()->where('slug', 'laptops')->value('id');

        $this->assertSame($phonesCategoryId, $viewed->category_id);
        $this->assertNotNull($laptopsCategoryId);

        $response = $this->getJson("/api/v1/products/recommended?product_ids={$viewed->id}&limit=12")
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = collect($response->json('data'));

        $this->assertGreaterThan(0, $items->count());
        $this->assertFalse($items->contains(fn (array $row) => (int) $row['id'] === $viewed->id));

        $phoneMatches = $items->filter(fn (array $row) => (int) $row['category_id'] === $phonesCategoryId);
        $laptopMatches = $items->filter(fn (array $row) => (int) $row['category_id'] === $laptopsCategoryId);

        $this->assertGreaterThan(0, $phoneMatches->count());
        $this->assertGreaterThanOrEqual($laptopMatches->count(), $phoneMatches->count());

        $firstLaptopIndex = $items->search(fn (array $row) => (int) $row['category_id'] === $laptopsCategoryId);
        $lastPhoneIndex = $items->keys()->filter(
            fn (int $index) => (int) $items[$index]['category_id'] === $phonesCategoryId,
        )->last();

        if ($firstLaptopIndex !== false && $lastPhoneIndex !== false) {
            $this->assertLessThan($firstLaptopIndex, $lastPhoneIndex);
        }
    }

    public function test_recommended_products_fallback_to_latest_when_history_empty(): void
    {
        $response = $this->getJson('/api/v1/products/recommended?limit=5')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = collect($response->json('data'));

        $this->assertGreaterThan(0, $items->count());
        $this->assertLessThanOrEqual(5, $items->count());
    }

    public function test_recommended_products_respect_platform_limit_cap(): void
    {
        app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
            'index_recommended_products_count' => 6,
        ], 'homepage');

        $response = $this->getJson('/api/v1/products/recommended?limit=12')
            ->assertOk();

        $items = collect($response->json('data'));

        $this->assertLessThanOrEqual(6, $items->count());
    }

    public function test_recommended_products_limit_never_exceeds_ten(): void
    {
        app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
            'index_recommended_products_count' => 15,
        ], 'homepage');

        $response = $this->getJson('/api/v1/products/recommended?limit=20')
            ->assertOk();

        $items = collect($response->json('data'));

        $this->assertLessThanOrEqual(10, $items->count());
    }

    public function test_recommended_products_include_images_for_hover_cards(): void
    {
        $response = $this->getJson('/api/v1/products/recommended?limit=5')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = collect($response->json('data'));

        $this->assertGreaterThan(0, $items->count());
        $this->assertTrue(
            $items->every(fn (array $row) => array_key_exists('images', $row) && is_array($row['images'])),
        );
        $this->assertTrue(
            $items->contains(fn (array $row) => count($row['images'] ?? []) >= 2),
            'Expected at least one recommended product with multiple images.',
        );
    }
}
