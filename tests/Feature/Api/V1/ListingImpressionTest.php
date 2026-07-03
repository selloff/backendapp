<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductListingDailyMetric;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListingImpressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_listing_impressions_are_recorded_and_deduped(): void
    {
        Cache::flush();

        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        $this->postJson('/api/v1/products/listing-impressions', [
            'product_ids' => [$product->id],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recorded', 1);

        $this->assertSame(
            1,
            (int) ProductListingDailyMetric::query()
                ->where('product_id', $product->id)
                ->whereDate('metric_date', now()->toDateString())
                ->value('impressions'),
        );

        $this->postJson('/api/v1/products/listing-impressions', [
            'product_ids' => [$product->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.recorded', 0);

        $this->assertSame(
            1,
            (int) ProductListingDailyMetric::query()
                ->where('product_id', $product->id)
                ->whereDate('metric_date', now()->toDateString())
                ->value('impressions'),
        );
    }

    public function test_vendor_does_not_record_impressions_on_own_listing(): void
    {
        Cache::flush();

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('vendor_id', $vendor->id)->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->postJson('/api/v1/products/listing-impressions', [
            'product_ids' => [$product->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.recorded', 0);

        $this->assertSame(
            0,
            (int) ProductListingDailyMetric::query()
                ->where('product_id', $product->id)
                ->whereDate('metric_date', now()->toDateString())
                ->value('impressions'),
        );
    }
}
