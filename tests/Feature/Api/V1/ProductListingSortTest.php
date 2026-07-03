<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use Tests\TestCase;

class ProductListingSortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_newest_sort_ignores_location_priority(): void
    {
        $country = Country::query()->where('name', 'Nigeria')->firstOrFail();
        $lagos = State::query()->where('name', 'Lagos')->firstOrFail();
        $rivers = State::query()->create([
            'country_id' => $country->id,
            'name' => 'Rivers',
            'status' => true,
        ]);

        $categoryId = Product::query()->listed()->value('category_id');
        $this->assertNotNull($categoryId);

        $riversProduct = Product::query()->listed()->where('category_id', $categoryId)->firstOrFail();
        Product::query()
            ->listed()
            ->where('category_id', $categoryId)
            ->whereKeyNot($riversProduct->id)
            ->update(['created_at' => now()->subDays(10)]);

        Product::query()->whereKey($riversProduct->id)->update([
            'state_id' => $rivers->id,
            'country_id' => $country->id,
            'city_id' => null,
            'created_at' => now(),
        ]);

        $lagosProduct = Product::query()
            ->listed()
            ->where('category_id', $categoryId)
            ->whereKeyNot($riversProduct->id)
            ->where('state_id', $lagos->id)
            ->firstOrFail();

        $response = $this->getJson(
            "/api/v1/products?sort=newest&category_id={$categoryId}&priority_state_id={$lagos->id}&per_page=100",
        )->assertOk();

        $items = collect($response->json('data.data'));

        $this->assertSame($riversProduct->id, $items->first()['id']);
    }

    public function test_recommended_sort_prioritizes_promoted_products_in_category(): void
    {
        $categoryId = Product::query()->listed()->value('category_id');
        $this->assertNotNull($categoryId);

        $promoted = Product::query()->listed()->where('category_id', $categoryId)->firstOrFail();
        $promoted->update([
            'is_promoted' => true,
            'created_at' => now()->subDays(5),
        ]);

        $regular = Product::query()
            ->listed()
            ->where('category_id', $categoryId)
            ->whereKeyNot($promoted->id)
            ->orderByDesc('created_at')
            ->firstOrFail();
        $regular->update([
            'is_promoted' => false,
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$categoryId}&per_page=100")
            ->assertOk();

        $items = collect($response->json('data.data'));

        $this->assertSame($promoted->id, $items->first()['id']);
    }

    public function test_lowest_price_sort_orders_by_discounted_price(): void
    {
        $categoryId = Product::query()->listed()->value('category_id');
        $products = Product::query()->listed()->where('category_id', $categoryId)->limit(2)->get();
        $this->assertCount(2, $products);

        $products[0]->update(['price' => 50000, 'price_discounted' => 45000]);
        $products[1]->update(['price' => 40000, 'price_discounted' => null]);

        $response = $this->getJson("/api/v1/products?sort=price&direction=asc&category_id={$categoryId}&per_page=100")
            ->assertOk();

        $items = collect($response->json('data.data'));
        $ids = $items->pluck('id');

        $this->assertTrue($ids->search($products[1]->id) < $ids->search($products[0]->id));
    }
}
