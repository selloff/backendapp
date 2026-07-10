<?php

use App\Modules\Selloff\Catalog\Services\ListingRankScoreService;
use App\Services\Platform\PlatformSettingsService;

describe('ListingRankScoreService', function () {
    beforeEach(function () {
        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    });

    test('reads featured products sort setting from platform settings', function () {
        app(PlatformSettingsService::class)->upsertMany([
            'sort_by_featured_products' => false,
        ], 'product');

        expect(app(ListingRankScoreService::class)->featuredProductsSortEnabled())->toBeFalse();
    });

    test('builds sqlite compatible top boost weight sql', function () {
        $sql = app(ListingRankScoreService::class)->effectiveTopBoostWeightSql();

        expect($sql)->toContain('products.top_boost_active = 1');
        expect($sql)->toContain("datetime('now')");
    });

    test('builds sqlite compatible visibility recency score sql', function () {
        $sql = app(ListingRankScoreService::class)->visibilityRecencyScoreSql();

        expect($sql)->toContain('user_membership_plans');
        expect($sql)->toContain("strftime('%s'");
    });
});
