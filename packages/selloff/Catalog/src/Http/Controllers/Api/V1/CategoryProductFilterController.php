<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Services\ProductFilterFacetService;
use App\Modules\Selloff\Catalog\Services\ProductFilterFieldResolver;
use App\Modules\Selloff\Catalog\Support\ProductListingFilterCriteria;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryProductFilterController extends Controller
{
    public function __construct(
        private readonly ProductFilterFacetService $facets,
        private readonly ProductFilterFieldResolver $filterFields,
    ) {}

    public function index(Request $request, Category $category): JsonResponse
    {
        $knownKeys = $this->filterFields->knownFilterKeys();
        $criteria = ProductListingFilterCriteria::fromRequest($request, $knownKeys);
        $criteria = new ProductListingFilterCriteria(
            search: $criteria->search,
            categoryId: $category->id,
            vendorId: $criteria->vendorId,
            brandIds: $criteria->brandIds,
            minPrice: $criteria->minPrice,
            maxPrice: $criteria->maxPrice,
            promoted: $criteria->promoted,
            discounted: $criteria->discounted,
            priorityStateId: $criteria->priorityStateId,
            priorityCityId: $criteria->priorityCityId,
            customFieldFilters: $criteria->customFieldFilters,
        );

        return ApiResponse::success($this->facets->filtersForCategory($category->id, $criteria));
    }

    public function options(Request $request, Category $category, string $filterKey): JsonResponse
    {
        $knownKeys = $this->filterFields->knownFilterKeys();
        abort_unless(in_array($filterKey, $knownKeys, true), 404, 'Filter not found.');

        $criteria = ProductListingFilterCriteria::fromRequest($request, $knownKeys);
        $criteria = new ProductListingFilterCriteria(
            search: $criteria->search,
            categoryId: $category->id,
            vendorId: $criteria->vendorId,
            brandIds: $criteria->brandIds,
            minPrice: $criteria->minPrice,
            maxPrice: $criteria->maxPrice,
            promoted: $criteria->promoted,
            discounted: $criteria->discounted,
            priorityStateId: $criteria->priorityStateId,
            priorityCityId: $criteria->priorityCityId,
            customFieldFilters: $criteria->customFieldFilters,
        );

        $page = max($request->integer('page', 1), 1);
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $search = trim((string) $request->input('q', ''));

        $result = $this->facets->optionsForFilterKey(
            $category->id,
            $filterKey,
            $criteria,
            $search,
            $page,
            $perPage,
        );

        return ApiResponse::success($result);
    }
}
