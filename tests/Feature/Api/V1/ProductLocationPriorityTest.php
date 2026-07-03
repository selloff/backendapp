<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use Tests\TestCase;

class ProductLocationPriorityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_query_builder_prioritizes_selected_state(): void
    {
        $country = Country::query()->where('name', 'Nigeria')->firstOrFail();
        $lagos = State::query()->where('name', 'Lagos')->firstOrFail();
        $rivers = State::query()->create([
            'country_id' => $country->id,
            'name' => 'Rivers',
            'status' => true,
        ]);

        $template = Product::query()->listed()->firstOrFail();
        Product::query()->whereKey($template->id)->update([
            'state_id' => $rivers->id,
            'country_id' => $country->id,
            'city_id' => null,
        ]);

        $query = Product::query()->listed();
        ProductLocationPriorityQuery::apply($query, $lagos->id, null);
        $query->orderBy('created_at', 'desc');

        $this->assertSame($lagos->id, $query->first()?->state_id);
    }

    public function test_products_index_prioritizes_selected_state(): void
    {
        $country = Country::query()->where('name', 'Nigeria')->firstOrFail();
        $lagos = State::query()->where('name', 'Lagos')->firstOrFail();
        $rivers = State::query()->create([
            'country_id' => $country->id,
            'name' => 'Rivers',
            'status' => true,
        ]);

        $template = Product::query()->listed()->firstOrFail();

        Product::query()->whereKey($template->id)->update([
            'state_id' => $rivers->id,
            'country_id' => $country->id,
            'city_id' => null,
        ]);

        $lagosProduct = Product::query()->listed()->where('state_id', $lagos->id)->firstOrFail();

        $response = $this->getJson("/api/v1/products?priority_state_id={$lagos->id}&per_page=100")
            ->assertOk();

        $items = collect($response->json('data.data'));

        $this->assertSame($lagos->id, (int) $items->first()['state_id']);

        $riversProductIndex = $items->search(fn (array $row) => $row['id'] === $template->id);
        $this->assertNotFalse($riversProductIndex);
        $this->assertGreaterThan(0, $riversProductIndex);
        $this->assertSame($lagosProduct->id, $items->first()['id']);
    }

    public function test_products_index_prioritizes_city_then_same_state(): void
    {
        $country = Country::query()->where('name', 'Nigeria')->firstOrFail();
        $lagos = State::query()->where('name', 'Lagos')->firstOrFail();
        $ikeja = City::query()->where('state_id', $lagos->id)->where('name', 'Ikeja')->firstOrFail();
        $lekki = City::query()->create([
            'state_id' => $lagos->id,
            'name' => 'Lekki',
            'status' => true,
        ]);

        $rivers = State::query()->create([
            'country_id' => $country->id,
            'name' => 'Rivers',
            'status' => true,
        ]);

        $cityProduct = Product::query()->listed()->firstOrFail();
        Product::query()->whereKey($cityProduct->id)->update([
            'country_id' => $country->id,
            'state_id' => $lagos->id,
            'city_id' => $ikeja->id,
        ]);

        $stateProduct = Product::query()->listed()->whereKeyNot($cityProduct->id)->firstOrFail();
        Product::query()->whereKey($stateProduct->id)->update([
            'country_id' => $country->id,
            'state_id' => $lagos->id,
            'city_id' => $lekki->id,
        ]);

        $otherStateProduct = Product::query()->listed()->whereKeyNot([$cityProduct->id, $stateProduct->id])->firstOrFail();
        Product::query()->whereKey($otherStateProduct->id)->update([
            'country_id' => $country->id,
            'state_id' => $rivers->id,
            'city_id' => null,
        ]);

        $ids = collect(
            $this->getJson("/api/v1/products?priority_state_id={$lagos->id}&priority_city_id={$ikeja->id}&per_page=100")
                ->assertOk()
                ->json('data.data')
        );

        $cityIndex = $ids->search(fn (array $row) => $row['id'] === $cityProduct->id);
        $stateIndex = $ids->search(fn (array $row) => $row['id'] === $stateProduct->id);
        $otherIndex = $ids->search(fn (array $row) => $row['id'] === $otherStateProduct->id);

        $this->assertNotFalse($cityIndex);
        $this->assertNotFalse($stateIndex);
        $this->assertNotFalse($otherIndex);
        $this->assertLessThan($stateIndex, $cityIndex);
        $this->assertLessThan($otherIndex, $stateIndex);
    }

    public function test_priority_query_helper_orders_state_tiers(): void
    {
        $query = Product::query()->listed()->select('products.*');
        ProductLocationPriorityQuery::apply($query, 99, null);

        $sql = $query->toSql();
        $this->assertStringContainsString('CASE WHEN state_id = ? THEN 0 ELSE 1 END', $sql);
        $this->assertContains(99, $query->getBindings());
    }
}
