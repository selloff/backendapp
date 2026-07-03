<?php

namespace Tests\Feature\LegacyImport;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorListingPerformanceLegacyImporterTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/product-pageviews-import.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
        $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();
    }

    public function test_legacy_pageviews_seed_listing_daily_metrics(): void
    {
        $totalTraffic = (int) DB::table('product_listing_daily_metrics')
            ->where('product_id', 96001)
            ->sum('traffic');

        $this->assertSame(1847, $totalTraffic);
        $this->assertSame(1847, (int) DB::table('products')->where('id', 96001)->value('pageviews'));
        $this->assertGreaterThan(0, DB::table('product_listing_daily_metrics')->where('product_id', 96001)->count());
        $this->assertSame(0, DB::table('product_listing_daily_metrics')->where('product_id', 96002)->count());
    }

    public function test_legacy_pageviews_seed_vendor_daily_metrics(): void
    {
        $totalTraffic = (int) DB::table('vendor_listing_daily_metrics')
            ->where('vendor_id', 94001)
            ->sum('traffic');

        $this->assertSame(1847, $totalTraffic);
    }

    public function test_sync_command_rebuilds_metrics_from_products_pageviews(): void
    {
        DB::table('product_listing_daily_metrics')->delete();
        DB::table('vendor_listing_daily_metrics')->delete();

        $this->artisan('selloff:sync-listing-performance-metrics')->assertSuccessful();

        $this->assertSame(
            1847,
            (int) DB::table('product_listing_daily_metrics')->where('product_id', 96001)->sum('traffic'),
        );
    }

    public function test_vendor_performance_summary_reflects_imported_metrics(): void
    {
        $vendor = User::query()->findOrFail(94001);
        Sanctum::actingAs($vendor);

        $response = $this->getJson('/api/v1/vendor/listing-performance?period=1y');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.traffic', 1847)
            ->assertJsonCount(1, 'data.top_listings')
            ->assertJsonPath('data.top_listings.0.product_id', 96001)
            ->assertJsonPath('data.top_listings.0.views', 1847);
    }
}
