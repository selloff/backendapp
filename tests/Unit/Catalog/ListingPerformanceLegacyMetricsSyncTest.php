<?php

namespace Tests\Unit\Catalog;

use App\Modules\Selloff\Catalog\Services\ListingPerformanceLegacyMetricsSync;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ListingPerformanceLegacyMetricsSyncTest extends TestCase
{
    public function test_distribute_views_preserves_total(): void
    {
        $sync = new ListingPerformanceLegacyMetricsSync;

        $distribution = $sync->distributeViews(
            1847,
            Carbon::parse('2022-01-01'),
            Carbon::parse('2026-06-28'),
        );

        $this->assertSame(1847, array_sum($distribution));
    }

    public function test_distribution_for_recent_product_uses_full_lifespan(): void
    {
        Carbon::setTestNow('2026-06-28 12:00:00');

        $sync = new ListingPerformanceLegacyMetricsSync;
        $distribution = $sync->distributionForProduct(100, Carbon::parse('2026-06-26'));

        $this->assertSame(100, array_sum($distribution));
        $this->assertSame(3, count($distribution));

        Carbon::setTestNow();
    }

    public function test_distribution_caps_at_one_year_for_old_listings(): void
    {
        Carbon::setTestNow('2026-06-28 12:00:00');

        $sync = new ListingPerformanceLegacyMetricsSync;
        $distribution = $sync->distributionForProduct(1847, Carbon::parse('2022-01-01'));

        $this->assertSame(1847, array_sum($distribution));
        $this->assertSame(365, count($distribution));
        $this->assertSame('2025-06-29', array_key_first($distribution));
        $this->assertSame('2026-06-28', array_key_last($distribution));

        Carbon::setTestNow();
    }
}
