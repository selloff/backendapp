<?php

use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('recommended products prefer same category as viewed history', function () {
    $viewed = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
    $phonesCategoryId = Category::query()->where('slug', 'smartphones')->value('id');
    $laptopsCategoryId = Category::query()->where('slug', 'laptops')->value('id');

    expect($viewed->category_id)->toBe($phonesCategoryId);
    expect($laptopsCategoryId)->not->toBeNull();

    $response = $this->getJson("/api/v1/products/recommended?product_ids={$viewed->id}&limit=12")
        ->assertOk()
        ->assertJsonPath('success', true);

    $items = collect($response->json('data'));

    expect($items->count())->toBeGreaterThan(0);
    expect($items->contains(fn (array $row) => (int) $row['id'] === $viewed->id))->toBeFalse();

    $phoneMatches = $items->filter(fn (array $row) => (int) $row['category_id'] === $phonesCategoryId);
    $laptopMatches = $items->filter(fn (array $row) => (int) $row['category_id'] === $laptopsCategoryId);

    expect($phoneMatches->count())->toBeGreaterThan(0);
    expect($phoneMatches->count())->toBeGreaterThanOrEqual($laptopMatches->count());

    $firstLaptopIndex = $items->search(fn (array $row) => (int) $row['category_id'] === $laptopsCategoryId);
    $lastPhoneIndex = $items->keys()->filter(
        fn (int $index) => (int) $items[$index]['category_id'] === $phonesCategoryId,
    )->last();

    if ($firstLaptopIndex !== false && $lastPhoneIndex !== false) {
        expect($lastPhoneIndex)->toBeLessThan($firstLaptopIndex);
    }
});

test('recommended products fallback to latest when history empty', function () {
    $response = $this->getJson('/api/v1/products/recommended?limit=5')
        ->assertOk()
        ->assertJsonPath('success', true);

    $items = collect($response->json('data'));

    expect($items->count())->toBeGreaterThan(0);
    expect($items->count())->toBeLessThanOrEqual(5);
});

test('recommended products respect platform limit cap', function () {
    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'index_recommended_products_count' => 6,
    ], 'homepage');

    $response = $this->getJson('/api/v1/products/recommended?limit=12')
        ->assertOk();

    $items = collect($response->json('data'));

    expect($items->count())->toBeLessThanOrEqual(6);
});

test('recommended products limit never exceeds ten', function () {
    app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
        'index_recommended_products_count' => 15,
    ], 'homepage');

    $response = $this->getJson('/api/v1/products/recommended?limit=20')
        ->assertOk();

    $items = collect($response->json('data'));

    expect($items->count())->toBeLessThanOrEqual(10);
});

test('recommended products include images for hover cards', function () {
    $response = $this->getJson('/api/v1/products/recommended?limit=5')
        ->assertOk()
        ->assertJsonPath('success', true);

    $items = collect($response->json('data'));

    expect($items->count())->toBeGreaterThan(0);
    expect($items->every(fn (array $row) => array_key_exists('images', $row) && is_array($row['images'])))->toBeTrue();
    expect($items->contains(fn (array $row) => count($row['images'] ?? []) >= 2))->toBeTrue('Expected at least one recommended product with multiple images.');
});
