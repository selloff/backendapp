<?php

namespace Tests\Feature\Api\V1;

use App\Modules\Selloff\Catalog\Services\VendorProductListingStatsService;
use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductListingDailyMetric;
use App\Modules\Selloff\Messaging\Models\Conversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorProductListingStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_products_include_lifetime_listing_stats(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('vendor_id', $vendor->id)->vendorItemsForSale()->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

        $this->assertTrue(Schema::hasColumn('product_listing_daily_metrics', 'impressions'));

        ProductListingDailyMetric::query()->where('product_id', $product->id)->delete();
        $product->update(['pageviews' => 99]);

        DB::table('product_listing_daily_metrics')->insert([
            [
                'vendor_id' => $vendor->id,
                'product_id' => $product->id,
                'metric_date' => now()->subDays(30)->toDateString(),
                'traffic' => 5,
                'visitors' => 3,
                'contact_views' => 2,
                'impressions' => 10,
                'chats' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'vendor_id' => $vendor->id,
                'product_id' => $product->id,
                'metric_date' => now()->toDateString(),
                'traffic' => 12,
                'visitors' => 8,
                'contact_views' => 3,
                'impressions' => 45,
                'chats' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Conversation::query()->create([
            'product_id' => $product->id,
            'sender_id' => $buyer->id,
            'receiver_id' => $vendor->id,
            'subject' => 'Question about listing',
        ]);

        $stats = app(VendorProductListingStatsService::class)->lifetimeForProducts(
            [$product->id],
            [$product->id => 99],
        );
        $this->assertSame(55, $stats[$product->id]['impressions']);
        $this->assertSame(99, $stats[$product->id]['pageviews']);
        $this->assertSame(5, $stats[$product->id]['phone_views']);
        $this->assertSame(1, $stats[$product->id]['chats']);

        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/v1/vendor/products?per_page=100');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $row = collect($response->json('data.data'))->firstWhere('id', $product->id);
        $this->assertIsArray($row);
        $this->assertSame(55, $row['listing_stats']['impressions']);
        $this->assertSame(99, $row['listing_stats']['pageviews']);
        $this->assertSame(5, $row['listing_stats']['phone_views']);
        $this->assertSame(1, $row['listing_stats']['chats']);
    }
}
