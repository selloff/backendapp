<?php

use App\Modules\Selloff\Catalog\Services\ListingPerformanceLegacyMetricsSync;
use Illuminate\Support\Carbon;

test('distribute views preserves total', function () {
    $sync = new ListingPerformanceLegacyMetricsSync;

    $distribution = $sync->distributeViews(
        1847,
        Carbon::parse('2022-01-01'),
        Carbon::parse('2026-06-28'),
    );

    expect(array_sum($distribution))->toBe(1847);
});

test('distribution for recent product uses full lifespan', function () {
    Carbon::setTestNow('2026-06-28 12:00:00');

    $sync = new ListingPerformanceLegacyMetricsSync;
    $distribution = $sync->distributionForProduct(100, Carbon::parse('2026-06-26'));

    expect(array_sum($distribution))->toBe(100);
    expect(count($distribution))->toBe(3);

    Carbon::setTestNow();
});

test('distribution caps at one year for old listings', function () {
    Carbon::setTestNow('2026-06-28 12:00:00');

    $sync = new ListingPerformanceLegacyMetricsSync;
    $distribution = $sync->distributionForProduct(1847, Carbon::parse('2022-01-01'));

    expect(array_sum($distribution))->toBe(1847);
    expect(count($distribution))->toBe(365);
    expect(array_key_first($distribution))->toBe('2025-06-29');
    expect(array_key_last($distribution))->toBe('2026-06-28');

    Carbon::setTestNow();
});
