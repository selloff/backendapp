<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrendingProductTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_homepage_includes_trending_products_section(): void
    {
        $this->getJson('/api/v1/homepage')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'trending_products',
                    'settings' => [
                        'index_trending_products_count',
                    ],
                ],
            ]);
    }

    public function test_trending_products_rank_by_recent_engagement_metrics(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $categoryId = Product::query()->value('category_id');

        DB::table('product_listing_daily_metrics')->delete();

        $quiet = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $categoryId,
        ]);

        $hot = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $categoryId,
        ]);

        $today = now()->toDateString();

        DB::table('product_listing_daily_metrics')->insert([
            [
                'vendor_id' => $vendor->id,
                'product_id' => $quiet->id,
                'metric_date' => $today,
                'traffic' => 2,
                'visitors' => 1,
                'contact_views' => 0,
                'impressions' => 5,
                'chats' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'vendor_id' => $vendor->id,
                'product_id' => $hot->id,
                'metric_date' => $today,
                'traffic' => 40,
                'visitors' => 20,
                'contact_views' => 6,
                'impressions' => 120,
                'chats' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/homepage')->assertOk();
        $trendingIds = collect($response->json('data.trending_products'))->pluck('id')->all();

        $this->assertNotEmpty($trendingIds);
        $this->assertSame($hot->id, $trendingIds[0]);
        $this->assertLessThan(
            array_search($quiet->id, $trendingIds, true),
            array_search($hot->id, $trendingIds, true),
        );
    }
}
