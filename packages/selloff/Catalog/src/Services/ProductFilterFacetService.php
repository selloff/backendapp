<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Support\ProductListingFilterCriteria;
use Illuminate\Support\Facades\DB;

class ProductFilterFacetService
{
    public function __construct(
        private readonly ProductListingFilterQuery $listingQuery,
        private readonly ProductFilterFieldResolver $filterFields,
        private readonly CategoryPathService $categoryPaths,
    ) {}

    /**
     * @return array{
     *     category_id: int,
     *     filters: list<array<string, mixed>>,
     *     price_buckets: list<array<string, mixed>>
     * }
     */
    public function filtersForCategory(int $categoryId, ProductListingFilterCriteria $criteria): array
    {
        $limit = (int) config('selloff.catalog.custom_filters_load_limit', 50);
        $collapseLimit = (int) config('selloff.catalog.custom_filters_collapse_limit', 3);

        $definitions = $this->filterFields->filterDefinitionsForCategory($categoryId);
        $filters = [];
        $index = 0;

        foreach ($definitions as $definition) {
            $facetCriteria = $criteria->withCustomFieldFiltersExcept($definition->key);
            $activeValues = $criteria->customFieldFilters[$definition->key] ?? [];
            if ($definition->key === 'brand') {
                $activeValues = array_map('strval', $criteria->brandIds);
            }

            if ($definition->key === 'brand') {
                $optionsResult = $this->brandOptions($categoryId, $facetCriteria, '', 1, $limit);
            } else {
                $optionsResult = $this->customFieldOptions(
                    (int) $definition->id,
                    $definition->key,
                    $facetCriteria,
                    '',
                    1,
                    $limit,
                    $definition->sort_options,
                );
            }

            $filters[] = [
                'id' => $definition->id,
                'key' => $definition->key,
                'label' => $definition->label,
                'type' => $definition->type,
                'collapsed_default' => $index >= $collapseLimit && $activeValues === [],
                'options' => $optionsResult['options'],
                'has_more' => $optionsResult['has_more'],
                'searchable' => count($optionsResult['options']) > 11 || $optionsResult['has_more'],
            ];

            $index++;
        }

        return [
            'category_id' => $categoryId,
            'filters' => $filters,
            'price_buckets' => $this->priceBuckets($categoryId, $criteria),
        ];
    }

    /**
     * @return array{options: list<array{value: string, label: string, count: int}>, has_more: bool, total: int}
     */
    public function optionsForFilterKey(
        int $categoryId,
        string $filterKey,
        ProductListingFilterCriteria $criteria,
        string $search = '',
        int $page = 1,
        int $perPage = 50,
    ): array {
        $perPage = min(max($perPage, 1), 100);
        $facetCriteria = $criteria->withCustomFieldFiltersExcept($filterKey);

        if ($filterKey === 'brand') {
            return $this->brandOptions($categoryId, $facetCriteria, $search, $page, $perPage);
        }

        $fieldId = $this->filterFields->resolveCustomFieldIdByKey($filterKey);
        if ($fieldId === null) {
            return ['options' => [], 'has_more' => false, 'total' => 0];
        }

        $field = CustomField::query()->find($fieldId);
        $sortOptions = $field?->sort_options;

        return $this->customFieldOptions($fieldId, $filterKey, $facetCriteria, $search, $page, $perPage, $sortOptions);
    }

    /**
     * @return array{options: list<array{value: string, label: string, count: int}>, has_more: bool, total: int}
     */
    private function brandOptions(
        int $categoryId,
        ProductListingFilterCriteria $criteria,
        string $search,
        int $page,
        int $perPage,
    ): array {
        $categoryIds = $this->categoryPaths->ancestorIdsIncludingSelf($categoryId);
        $productIds = (clone $this->listingQuery->baseListedQuery($criteria))->pluck('id');

        if ($productIds->isEmpty()) {
            return ['options' => [], 'has_more' => false, 'total' => 0];
        }

        $query = DB::table('brands')
            ->join('products', 'products.brand_id', '=', 'brands.id')
            ->whereIn('products.id', $productIds)
            ->when($categoryIds !== [], function ($q) use ($categoryIds): void {
                $q->join('brand_category', 'brand_category.brand_id', '=', 'brands.id')
                    ->whereIn('brand_category.category_id', $categoryIds);
            })
            ->when($search !== '', fn ($q) => $q->where('brands.name', 'like', '%'.$search.'%'))
            ->groupBy('brands.id', 'brands.name')
            ->select('brands.id', 'brands.name', DB::raw('count(distinct products.id) as count'))
            ->orderBy('brands.name');

        $total = DB::query()->fromSub($query, 'brand_counts')->count();
        $offset = ($page - 1) * $perPage;
        $rows = $query->offset($offset)->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage);

        return [
            'options' => $rows->map(fn ($row) => [
                'value' => (string) $row->id,
                'label' => (string) $row->name,
                'count' => (int) $row->count,
            ])->values()->all(),
            'has_more' => $hasMore,
            'total' => $total,
        ];
    }

    /**
     * @return array{options: list<array{value: string, label: string, count: int}>, has_more: bool, total: int}
     */
    private function customFieldOptions(
        int $fieldId,
        string $filterKey,
        ProductListingFilterCriteria $criteria,
        string $search,
        int $page,
        int $perPage,
        ?string $sortOptions,
    ): array {
        unset($filterKey);

        $productIds = (clone $this->listingQuery->baseListedQuery($criteria))->pluck('id');

        $counts = [];
        if ($productIds->isNotEmpty()) {
            $counts = DB::table('custom_field_product as cfp')
                ->join('custom_field_options as cfo', 'cfo.id', '=', 'cfp.custom_field_option_id')
                ->where('cfp.custom_field_id', $fieldId)
                ->whereIn('cfp.product_id', $productIds)
                ->groupBy('cfo.option_key')
                ->select('cfo.option_key', DB::raw('count(distinct cfp.product_id) as count'))
                ->pluck('count', 'option_key')
                ->map(fn ($count) => (int) $count)
                ->all();
        }

        $query = DB::table('custom_field_options as cfo')
            ->where('cfo.custom_field_id', $fieldId)
            ->when($search !== '', fn ($q) => $q->where('cfo.label', 'like', '%'.$search.'%'))
            ->select('cfo.option_key', 'cfo.label');

        if ($sortOptions === 'alpha_desc') {
            $query->orderByDesc('cfo.label');
        } else {
            $query->orderBy('cfo.label');
        }

        $total = (clone $query)->count();
        $offset = ($page - 1) * $perPage;
        $rows = $query->offset($offset)->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage);

        return [
            'options' => $rows->map(fn ($row) => [
                'value' => (string) $row->option_key,
                'label' => (string) $row->label,
                'count' => $counts[$row->option_key] ?? 0,
            ])->values()->all(),
            'has_more' => $hasMore,
            'total' => $total,
        ];
    }

    /**
     * @return list<array{min: float|null, max: float|null, label: string, count: int}>
     */
    private function priceBuckets(int $categoryId, ProductListingFilterCriteria $criteria): array
    {
        $facetCriteria = new ProductListingFilterCriteria(
            search: $criteria->search,
            categoryId: $categoryId,
            vendorId: $criteria->vendorId,
            brandIds: $criteria->brandIds,
            minPrice: null,
            maxPrice: null,
            promoted: $criteria->promoted,
            discounted: $criteria->discounted,
            priorityStateId: $criteria->priorityStateId,
            priorityCityId: $criteria->priorityCityId,
            customFieldFilters: $criteria->customFieldFilters,
        );

        $baseQuery = $this->listingQuery->baseListedQuery($facetCriteria);
        $prices = (clone $baseQuery)
            ->selectRaw('CAST(COALESCE(price_discounted, price) AS REAL) as effective_price')
            ->pluck('effective_price')
            ->map(fn ($price) => (float) $price)
            ->filter(fn (float $price) => $price > 0)
            ->sort()
            ->values();

        if ($prices->isEmpty()) {
            return [];
        }

        $min = $prices->first();
        $max = $prices->last();

        if ($min === $max) {
            return [[
                'min' => $min,
                'max' => $max,
                'label' => $this->formatPriceLabel($min, $max),
                'count' => $prices->count(),
            ]];
        }

        $bucketCount = min(5, max(3, (int) ceil($prices->count() / 20)));
        $step = ($max - $min) / $bucketCount;
        $buckets = [];

        for ($i = 0; $i < $bucketCount; $i++) {
            $bucketMin = $i === 0 ? null : round($min + ($step * $i), 2);
            $bucketMax = $i === $bucketCount - 1 ? null : round($min + ($step * ($i + 1)), 2);

            $count = $prices->filter(function (float $price) use ($bucketMin, $bucketMax, $i, $bucketCount, $min, $max): bool {
                $lower = $bucketMin ?? $min;
                $upper = $bucketMax ?? $max;
                if ($i === 0) {
                    return $price <= $upper;
                }
                if ($i === $bucketCount - 1) {
                    return $price > $lower;
                }

                return $price > $lower && $price <= $upper;
            })->count();

            if ($count === 0) {
                continue;
            }

            $buckets[] = [
                'min' => $bucketMin,
                'max' => $bucketMax,
                'label' => $this->formatPriceLabel($bucketMin, $bucketMax),
                'count' => $count,
            ];
        }

        return $buckets;
    }

    private function formatPriceLabel(?float $min, ?float $max): string
    {
        if ($min === null && $max !== null) {
            return 'Under '.$this->formatMoney($max);
        }

        if ($min !== null && $max === null) {
            return 'Over '.$this->formatMoney($min);
        }

        if ($min !== null && $max !== null) {
            return $this->formatMoney($min).' – '.$this->formatMoney($max);
        }

        return 'Any price';
    }

    private function formatMoney(float $amount): string
    {
        if ($amount >= 1_000_000) {
            return rtrim(rtrim(number_format($amount / 1_000_000, 1), '0'), '.').'M';
        }

        if ($amount >= 1_000) {
            return rtrim(rtrim(number_format($amount / 1_000, 0), '0'), '.').'K';
        }

        return number_format($amount, 0);
    }
}
