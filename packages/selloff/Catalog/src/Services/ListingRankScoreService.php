<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Support\ProductListingFilterCriteria;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListingRankScoreService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function featuredProductsSortEnabled(): bool
    {
        return filter_var(
            $this->settings->all()['sort_by_featured_products'] ?? true,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * @param  Builder<\App\Modules\Selloff\Catalog\Models\Product>  $query
     */
    public function apply(
        Builder $query,
        ProductListingFilterCriteria $criteria,
        bool $includePromotedTier = true,
    ): void {
        if ($includePromotedTier) {
            $query->orderByDesc('is_promoted');
        }

        $query->orderByRaw($this->effectiveTopBoostWeightSql().' DESC');
        $query->orderByRaw('('.$this->visibilityRecencyScoreSql().') DESC');
        ProductLocationPriorityQuery::apply($query, $criteria->priorityStateId, $criteria->priorityCityId);
        $query->orderByDesc('is_special_offer');
        $query->orderByDesc('created_at');
    }

    public function effectiveTopBoostWeightSql(): string
    {
        $now = $this->nowExpression();

        return match ($this->driver()) {
            'pgsql' => "CASE WHEN products.top_boost_active = true AND (products.top_boost_expires_at IS NULL OR products.top_boost_expires_at > {$now}) THEN COALESCE(products.top_boost_weight, 0) ELSE 0 END",
            default => "CASE WHEN products.top_boost_active = 1 AND (products.top_boost_expires_at IS NULL OR products.top_boost_expires_at > {$now}) THEN COALESCE(products.top_boost_weight, 0) ELSE 0 END",
        };
    }

    public function visibilityRecencyScoreSql(): string
    {
        return 'COALESCE('.$this->visibilityMultiplierSubquerySql().', 1) * '.$this->recencyEpochSql();
    }

    private function visibilityMultiplierSubquerySql(): string
    {
        $now = $this->nowExpression();
        $activeFlag = $this->driver() === 'pgsql' ? 'true' : '1';

        if ($this->driver() === 'pgsql') {
            $multiplier = "COALESCE(NULLIF((entitlements_snapshot->>'visibility_multiplier')::numeric, 0), 1)";
        } else {
            $multiplier = "COALESCE(NULLIF(CAST(json_extract(entitlements_snapshot, '$.visibility_multiplier') AS REAL), 0), 1)";
        }

        return "(SELECT {$multiplier} FROM user_membership_plans WHERE user_id = products.vendor_id AND is_active = {$activeFlag} AND (expires_at IS NULL OR expires_at > {$now}) ORDER BY id DESC LIMIT 1)";
    }

    private function recencyEpochSql(): string
    {
        return match ($this->driver()) {
            'pgsql' => "EXTRACT(EPOCH FROM COALESCE(products.last_bumped_at, products.created_at))",
            default => "CAST(strftime('%s', COALESCE(products.last_bumped_at, products.created_at)) AS REAL)",
        };
    }

    private function nowExpression(): string
    {
        return match ($this->driver()) {
            'pgsql' => 'CURRENT_TIMESTAMP',
            default => "datetime('now')",
        };
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }
}
