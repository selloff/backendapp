<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ProductListingFilterCriteria;
use App\Modules\Selloff\Payment\Services\MembershipCatalogVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductListingFilterQuery
{
    public function __construct(
        private readonly ProductFilterFieldResolver $filterFields,
        private readonly MembershipCatalogVisibilityService $membershipCatalogVisibility,
    ) {}

    /**
     * @param  Builder<Product>  $query
     */
    public function apply(Builder $query, ProductListingFilterCriteria $criteria): void
    {
        $categoryIds = $this->filterFields->categoryScopeIds($criteria->categoryId);

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        if ($criteria->vendorId) {
            $query->where('vendor_id', $criteria->vendorId);
        }

        if ($criteria->brandIds !== []) {
            $query->whereIn('brand_id', $criteria->brandIds);
        }

        if ($criteria->minPrice !== null) {
            $query->whereRaw('CAST(COALESCE(price_discounted, price) AS REAL) >= ?', [$criteria->minPrice]);
        }

        if ($criteria->maxPrice !== null) {
            $query->whereRaw('CAST(COALESCE(price_discounted, price) AS REAL) <= ?', [$criteria->maxPrice]);
        }

        if ($criteria->promoted) {
            $query->where('is_promoted', true);
        }

        if ($criteria->discounted) {
            $query->whereNotNull('price_discounted')
                ->whereColumn('price_discounted', '<', 'price');
        }

        if ($criteria->search) {
            $term = '%'.$criteria->search.'%';
            $query->whereHas('translations', fn (Builder $inner) => $inner
                ->where('title', 'like', $term)
                ->orWhere('description', 'like', $term));
        }

        foreach ($criteria->customFieldFilters as $filterKey => $optionKeys) {
            if ($filterKey === 'brand' || $optionKeys === []) {
                continue;
            }

            $fieldId = $this->filterFields->resolveCustomFieldIdByKey($filterKey);
            if ($fieldId === null) {
                continue;
            }

            $query->whereExists(function ($sub) use ($fieldId, $optionKeys): void {
                $sub->select(DB::raw(1))
                    ->from('custom_field_product as cfp')
                    ->join('custom_field_options as cfo', 'cfo.id', '=', 'cfp.custom_field_option_id')
                    ->whereColumn('cfp.product_id', 'products.id')
                    ->where('cfp.custom_field_id', $fieldId)
                    ->whereIn('cfo.option_key', $optionKeys);
            });
        }
    }

    /**
     * @return Builder<Product>
     */
    public function baseListedQuery(ProductListingFilterCriteria $criteria): Builder
    {
        $query = Product::query()->listed();
        $this->membershipCatalogVisibility->applyVendorMembershipVisibility($query);

        $this->apply($query, $criteria);

        return $query;
    }

    /**
     * @return Builder<Product>
     */
    public function fromCriteria(ProductListingFilterCriteria $criteria, array $with = [], array $withCount = []): Builder
    {
        $query = $this->baseListedQuery($criteria);

        if ($with !== []) {
            $query->with($with);
        }

        foreach ($withCount as $relation) {
            $query->withCount($relation);
        }

        return $query;
    }
}
