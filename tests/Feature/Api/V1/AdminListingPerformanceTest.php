<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can fetch platform listing performance summary', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $product = \App\Modules\Selloff\Catalog\Models\Product::query()->firstOrFail();
    Sanctum::actingAs($admin);

    $metricDate = now()->toDateString();
    \Illuminate\Support\Facades\DB::table('product_listing_daily_metrics')->insert([
        'vendor_id' => $product->vendor_id,
        'product_id' => $product->id,
        'metric_date' => $metricDate,
        'traffic' => 25,
        'visitors' => 10,
        'contact_views' => 2,
        'impressions' => 40,
        'chats' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $response = $this->getJson("/api/v1/admin/listing-performance?from={$from}&to={$to}&period_label=Last%207%20days");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.period', 'custom')
        ->assertJsonPath('data.period_label', 'Last 7 days')
        ->assertJsonStructure([
            'data' => [
                'period',
                'period_label',
                'range_label',
                'currency_code',
                'series',
                'totals' => [
                    'traffic',
                    'visitors',
                    'impressions',
                    'contact_views',
                    'chats',
                    'promotion_spend',
                ],
                'top_listings',
                'recent' => [
                    'contact_views',
                    'chats',
                ],
            ],
        ]);

    $topListing = $response->json('data.top_listings.0');
    expect($topListing)->toBeArray();
    expect($topListing['product_id'])->toBe($product->id);
    $this->assertNotSame('Listing #'.$product->id, $topListing['title']);
});
