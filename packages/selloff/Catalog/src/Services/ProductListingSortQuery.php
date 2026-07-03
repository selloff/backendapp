<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Support\ProductListingFilterCriteria;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use Illuminate\Database\Eloquent\Builder;

class ProductListingSortQuery
{
    public function __construct(
        private readonly ListingRankScoreService $listingRank,
    ) {}

    /**
     * @param  Builder<\App\Modules\Selloff\Catalog\Models\Product>  $query
     */
    public function apply(Builder $query, string $sort, string $direction, ProductListingFilterCriteria $criteria): void
    {
        $sort = $this->normalizeSort($sort);

        match ($sort) {
            'newest' => $query->orderBy('created_at', 'desc'),
            'recommended' => $this->applyRecommended($query, $criteria),
            'price' => $query->orderByRaw(
                'CAST(COALESCE(price_discounted, price) AS REAL) '.($direction === 'asc' ? 'ASC' : 'DESC'),
            ),
            'most_recent' => $this->applyMostRecent($query, $criteria),
            default => $query->orderBy('created_at', 'desc'),
        };
    }

    private function normalizeSort(string $sort): string
    {
        return match ($sort) {
            'created_at' => 'most_recent',
            default => $sort,
        };
    }

    /**
     * @param  Builder<\App\Modules\Selloff\Catalog\Models\Product>  $query
     */
    private function applyMostRecent(Builder $query, ProductListingFilterCriteria $criteria): void
    {
        if ($this->listingRank->featuredProductsSortEnabled()) {
            $this->listingRank->apply($query, $criteria);

            return;
        }

        ProductLocationPriorityQuery::apply($query, $criteria->priorityStateId, $criteria->priorityCityId);
        $query->orderBy('created_at', 'desc');
    }

    /**
     * Category-scoped listing recommendation: VIP promoted, membership rank, location, offers, recency.
     *
     * @param  Builder<\App\Modules\Selloff\Catalog\Models\Product>  $query
     */
    private function applyRecommended(Builder $query, ProductListingFilterCriteria $criteria): void
    {
        $this->listingRank->apply($query, $criteria);
    }
}
