<?php

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use App\Modules\Selloff\Location\Models\City;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);

    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'sort_by_featured_products' => false,
    ]);
});

test('query builder prioritizes selected state', function () {
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

    expect($query->first()?->state_id)->toBe($lagos->id);
});

test('products index prioritizes selected state', function () {
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

    $lagosProduct = Product::query()
        ->listed()
        ->where('state_id', $lagos->id)
        ->orderByDesc('created_at')
        ->firstOrFail();

    $response = $this->getJson("/api/v1/products?priority_state_id={$lagos->id}&per_page=100")
        ->assertOk();

    $items = collect($response->json('data.data'));

    expect((int) $items->first()['state_id'])->toBe($lagos->id);

    $riversProductIndex = $items->search(fn (array $row) => $row['id'] === $template->id);
    $this->assertNotFalse($riversProductIndex);
    expect($riversProductIndex)->toBeGreaterThan(0);
    expect($items->first()['id'])->toBe($lagosProduct->id);
});

test('products index prioritizes city then same state', function () {
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
    expect($cityIndex)->toBeLessThan($stateIndex);
    expect($stateIndex)->toBeLessThan($otherIndex);
});

test('priority query helper orders state tiers', function () {
    $query = Product::query()->listed()->select('products.*');
    ProductLocationPriorityQuery::apply($query, 99, null);

    $sql = $query->toSql();
    $this->assertStringContainsString('CASE WHEN state_id = ? THEN 0 ELSE 1 END', $sql);
    expect($query->getBindings())->toContain(99);
});
