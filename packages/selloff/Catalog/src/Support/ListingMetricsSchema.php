<?php

namespace App\Modules\Selloff\Catalog\Support;

use Illuminate\Support\Facades\Schema;

final class ListingMetricsSchema
{
    public static function hasProductDailyMetricsTable(): bool
    {
        return Schema::hasTable('product_listing_daily_metrics');
    }

    public static function hasImpressionsColumn(): bool
    {
        return self::hasProductDailyMetricsTable()
            && Schema::hasColumn('product_listing_daily_metrics', 'impressions');
    }

    public static function impressionsSumSql(): string
    {
        return self::hasImpressionsColumn() ? 'SUM(impressions) as impressions' : '0 as impressions';
    }
}
