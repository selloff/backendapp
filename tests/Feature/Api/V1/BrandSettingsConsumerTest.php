<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Brand;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('marketplace preferences expose brand settings', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'brand_status' => true,
        'is_brand_optional' => false,
        'brand_where_to_display' => 1,
    ], 'product');

    $this->getJson('/api/v1/preferences/marketplace')
        ->assertOk()
        ->assertJsonPath('data.brand_settings.brand_status', true)
        ->assertJsonPath('data.brand_settings.is_brand_optional', false)
        ->assertJsonPath('data.brand_settings.brand_where_to_display', 1);
});

test('public brands endpoint is empty when brands disabled', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'brand_status' => false,
    ], 'product');

    $this->getJson('/api/v1/brands')
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('public brands endpoint filters by category ancestors', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'brand_status' => true,
    ], 'product');

    $phones = Category::query()->where('slug', 'smartphones')->firstOrFail();
    $otherCategory = Category::query()->where('slug', 'fashion')->firstOrFail();

    $scopedBrand = Brand::query()->create(['name' => 'Scoped Consumer Brand']);
    $scopedBrand->categories()->sync([$phones->id]);
    $otherBrand = Brand::query()->create(['name' => 'Other Consumer Brand']);
    $otherBrand->categories()->sync([$otherCategory->id]);

    $response = $this->getJson("/api/v1/brands?category_id={$phones->id}")
        ->assertOk()
        ->json('data');

    $names = collect($response)->pluck('name')->all();
    expect($names)->toContain('Scoped Consumer Brand');
    expect($names)->not->toContain('Other Consumer Brand');
});

test('vendor publish requires brand when not optional', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'brand_status' => true,
        'is_brand_optional' => false,
    ], 'product');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $phones = Category::query()->where('slug', 'smartphones')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products', [
        'title' => 'Brand Required Product',
        'category_id' => $phones->id,
        'price' => 1000,
        'stock' => 1,
        'status' => 'published',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['brand_id']);
});

test('vendor can publish with brand when not optional', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'brand_status' => true,
        'is_brand_optional' => false,
    ], 'product');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $phones = Category::query()->where('slug', 'smartphones')->firstOrFail();
    $brand = Brand::query()->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products', [
        'title' => 'Brand Attached Product',
        'category_id' => $phones->id,
        'brand_id' => $brand->id,
        'price' => 1000,
        'stock' => 1,
        'status' => 'published',
    ])
        ->assertCreated()
        ->assertJsonPath('data.brand.id', $brand->id);
});

test('draft product does not require brand when not optional', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'brand_status' => true,
        'is_brand_optional' => false,
    ], 'product');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $phones = Category::query()->where('slug', 'smartphones')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products', [
        'title' => 'Draft Without Brand',
        'category_id' => $phones->id,
        'price' => 1000,
        'stock' => 1,
        'status' => 'draft',
    ])->assertCreated();
});
