<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ProductListingFilterCriteria;
use App\Modules\Selloff\Catalog\Support\ProductLocationPriorityQuery;
use Illuminate\Database\Eloquent\Collection;

class ProductRecommendationService
{
    public function __construct(
        private readonly ListingRankScoreService $listingRank,
    ) {}
    /**
     * @param  list<int>  $viewedProductIds
     * @return Collection<int, Product>
     */
    public function recommend(
        array $viewedProductIds,
        int $limit,
        ?int $priorityStateId = null,
        ?int $priorityCityId = null,
    ): Collection {
        $limit = min(max($limit, 1), 20);
        $viewedProductIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $viewedProductIds),
            static fn (int $id) => $id > 0,
        )));

        if ($viewedProductIds === []) {
            return $this->fallbackLatest($limit, $priorityStateId, $priorityCityId);
        }

        $viewed = Product::query()
            ->whereIn('id', $viewedProductIds)
            ->get(['id', 'category_id', 'brand_id']);

        $categoryIds = $viewed->pluck('category_id')->filter()->unique()->values()->all();
        $brandIds = $viewed->pluck('brand_id')->filter()->unique()->values()->all();

        $query = Product::query()
            ->listed()
            ->whereNotIn('id', $viewedProductIds)
            ->with(ProductResource::listEagerLoads())
            ->withCount('options');

        $scoreSql = '0';
        $bindings = [];

        if ($categoryIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($categoryIds), '?'));
            $scoreSql .= " + CASE WHEN category_id IN ({$placeholders}) THEN 10 ELSE 0 END";
            array_push($bindings, ...$categoryIds);
        }

        if ($brandIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($brandIds), '?'));
            $scoreSql .= " + CASE WHEN brand_id IN ({$placeholders}) THEN 5 ELSE 0 END";
            array_push($bindings, ...$brandIds);
        }

        if ($bindings !== []) {
            $query->orderByRaw("({$scoreSql}) DESC", $bindings);
        }

        ProductLocationPriorityQuery::apply($query, $priorityStateId, $priorityCityId);
        $query->orderBy('created_at', 'desc');

        return $query->limit($limit)->get();
    }

    /**
     * @return Collection<int, Product>
     */
    private function fallbackLatest(int $limit, ?int $priorityStateId, ?int $priorityCityId): Collection
    {
        $query = Product::query()
            ->listed()
            ->with(ProductResource::listEagerLoads())
            ->withCount('options');

        $criteria = new ProductListingFilterCriteria(
            priorityStateId: $priorityStateId,
            priorityCityId: $priorityCityId,
        );

        if ($this->listingRank->featuredProductsSortEnabled()) {
            $this->listingRank->apply($query, $criteria);
        } else {
            ProductLocationPriorityQuery::apply($query, $priorityStateId, $priorityCityId);
            $query->orderBy('created_at', 'desc');
        }

        return $query->limit($limit)->get();
    }
}
