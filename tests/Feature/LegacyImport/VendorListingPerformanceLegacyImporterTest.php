<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/product-pageviews-import.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();
});

test('legacy pageviews seed listing daily metrics', function () {
    $totalTraffic = (int) DB::table('product_listing_daily_metrics')
        ->where('product_id', 96001)
        ->sum('traffic');

    expect($totalTraffic)->toBe(1847);
    expect((int) DB::table('products')->where('id', 96001)->value('pageviews'))->toBe(1847);
    expect(DB::table('product_listing_daily_metrics')->where('product_id', 96001)->count())->toBeGreaterThan(0);
    expect(DB::table('product_listing_daily_metrics')->where('product_id', 96002)->count())->toBe(0);
});

test('legacy pageviews seed vendor daily metrics', function () {
    $totalTraffic = (int) DB::table('vendor_listing_daily_metrics')
        ->where('vendor_id', 94001)
        ->sum('traffic');

    expect($totalTraffic)->toBe(1847);
});

test('sync command rebuilds metrics from products pageviews', function () {
    DB::table('product_listing_daily_metrics')->delete();
    DB::table('vendor_listing_daily_metrics')->delete();

    $this->artisan('selloff:sync-listing-performance-metrics')->assertSuccessful();

    expect((int) DB::table('product_listing_daily_metrics')->where('product_id', 96001)->sum('traffic'))->toBe(1847);
});

test('vendor performance summary reflects imported metrics', function () {
    $vendor = User::query()->findOrFail(94001);
    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/v1/vendor/listing-performance?period=1y');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.totals.traffic', 1847)
        ->assertJsonCount(1, 'data.top_listings')
        ->assertJsonPath('data.top_listings.0.product_id', 96001)
        ->assertJsonPath('data.top_listings.0.views', 1847);
});