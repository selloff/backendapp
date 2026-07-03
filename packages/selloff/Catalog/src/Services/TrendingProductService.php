<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ListingMetricsSchema;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use App\Modules\Selloff\Payment\Services\MembershipCatalogVisibilityService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrendingProductService
{
    public const WINDOW_DAYS = 7;

    public const DEFAULT_LIMIT = 12;

    public function __construct(
        private readonly MembershipCatalogVisibilityService $membershipCatalogVisibility,
    ) {}

    /**
     * Rank listings by recent engagement (views, impressions, phone views, chats).
     *
     * @return Collection<int, Product>
     */
    public function forHomepage(
        int $limit,
        ?int $priorityStateId = null,
        ?int $priorityCityId = null,
    ): Collection {
        $limit = max(1, min($limit, 24));

        if (ListingMetricsSchema::hasProductDailyMetricsTable()) {
            $fromMetrics = $this->fromRecentMetrics($limit, $priorityStateId, $priorityCityId);
            if ($fromMetrics->isNotEmpty()) {
                return $fromMetrics;
            }
        }

        return $this->fromLifetimePageviews($limit, $priorityStateId, $priorityCityId);
    }

    /**
     * @return Collection<int, Product>
     */
    private function fromRecentMetrics(
        int $limit,
        ?int $priorityStateId,
        ?int $priorityCityId,
    ): Collection {
        $since = now()->subDays(self::WINDOW_DAYS)->toDateString();
        $scoreSql = $this->recentEngagementScoreSql();

        $rankedIds = DB::table('product_listing_daily_metrics')
            ->selectRaw("product_id, ({$scoreSql}) as trending_score")
            ->where('metric_date', '>=', $since)
            ->groupBy('product_id')
            ->havingRaw("({$scoreSql}) > 0")
            ->orderByDesc('trending_score')
            ->limit($limit * 4)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($rankedIds === []) {
            return new Collection;
        }

        return $this->loadOrderedProducts($rankedIds, $limit, $priorityStateId, $priorityCityId);
    }

    /**
     * @return Collection<int, Product>
     */
    private function fromLifetimePageviews(
        int $limit,
        ?int $priorityStateId,
        ?int $priorityCityId,
    ): Collection {
        $query = Product::query()
            ->listed()
            ->with(ProductResource::listEagerLoads())
            ->withCount('options')
            ->where('pageviews', '>', 0);

        $this->membershipCatalogVisibility->applyVendorMembershipVisibility($query);
        ProductLocationPriorityQuery::apply($query, $priorityStateId, $priorityCityId);

        return $query
            ->orderByDesc('pageviews')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  list<int>  $rankedIds
     * @return Collection<int, Product>
     */
    private function loadOrderedProducts(
        array $rankedIds,
        int $limit,
        ?int $priorityStateId,
        ?int $priorityCityId,
    ): Collection {
        $query = Product::query()
            ->listed()
            ->with(ProductResource::listEagerLoads())
            ->withCount('options')
            ->whereIn('id', $rankedIds);

        $this->membershipCatalogVisibility->applyVendorMembershipVisibility($query);
        ProductLocationPriorityQuery::apply($query, $priorityStateId, $priorityCityId);

        $products = $query->get()->keyBy('id');
        $ordered = new Collection;

        foreach ($rankedIds as $productId) {
            $product = $products->get($productId);
            if ($product === null) {
                continue;
            }

            $ordered->push($product);
            if ($ordered->count() >= $limit) {
                break;
            }
        }

        return $ordered;
    }

    private function recentEngagementScoreSql(): string
    {
        $parts = [
            'COALESCE(SUM(traffic), 0) * 3',
            'COALESCE(SUM(contact_views), 0) * 4',
        ];

        if (ListingMetricsSchema::hasImpressionsColumn()) {
            $parts[] = 'COALESCE(SUM(impressions), 0)';
        }

        if (Schema::hasColumn('product_listing_daily_metrics', 'chats')) {
            $parts[] = 'COALESCE(SUM(chats), 0) * 6';
        }

        return implode(' + ', $parts);
    }
}
