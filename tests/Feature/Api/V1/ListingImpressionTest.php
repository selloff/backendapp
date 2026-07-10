<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductListingDailyMetric;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('listing impressions are recorded and deduped', function () {
    Cache::flush();

    $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

    $this->postJson('/api/v1/products/listing-impressions', [
        'product_ids' => [$product->id],
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.recorded', 1);

    expect((int) ProductListingDailyMetric::query()
        ->where('product_id', $product->id)
        ->whereDate('metric_date', now()->toDateString())
        ->value('impressions'))->toBe(1);

    $this->postJson('/api/v1/products/listing-impressions', [
        'product_ids' => [$product->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.recorded', 0);

    expect((int) ProductListingDailyMetric::query()
        ->where('product_id', $product->id)
        ->whereDate('metric_date', now()->toDateString())
        ->value('impressions'))->toBe(1);
});

test('vendor does not record impressions on own listing', function () {
    Cache::flush();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products/listing-impressions', [
        'product_ids' => [$product->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.recorded', 0);

    expect((int) ProductListingDailyMetric::query()
        ->where('product_id', $product->id)
        ->whereDate('metric_date', now()->toDateString())
        ->value('impressions'))->toBe(0);
});
