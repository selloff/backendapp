<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminListingPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_fetch_platform_listing_performance_summary(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $product = \App\Modules\Selloff\Catalog\Models\Product::query()->firstOrFail();
        Sanctum::actingAs($admin);

        \Illuminate\Support\Facades\DB::table('product_listing_daily_metrics')->insert([
            'vendor_id' => $product->vendor_id,
            'product_id' => $product->id,
            'metric_date' => now()->toDateString(),
            'traffic' => 25,
            'visitors' => 10,
            'contact_views' => 2,
            'impressions' => 40,
            'chats' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/listing-performance?from=2026-06-01&to=2026-06-28&period_label=Last%2030%20days');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'custom')
            ->assertJsonPath('data.period_label', 'Last 30 days')
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
        $this->assertIsArray($topListing);
        $this->assertSame($product->id, $topListing['product_id']);
        $this->assertNotSame('Listing #'.$product->id, $topListing['title']);
    }
}
