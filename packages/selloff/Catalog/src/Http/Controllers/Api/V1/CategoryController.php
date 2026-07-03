<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\CategoryResource;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Services\CategoryListingCountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request, CategoryListingCountService $listingCounts): JsonResponse
    {
        $query = Category::query()
            ->with([
                'translations',
                'children' => fn ($q) => $q->where('status', true)->orderBy('category_order'),
                'children.translations',
                'children.children' => fn ($q) => $q->where('status', true)->orderBy('category_order'),
                'children.children.translations',
            ])
            ->withCount(['children as children_count' => fn ($q) => $q->where('status', true)])
            ->where('status', true)
            ->when($request->boolean('roots_only'), fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('category_order');

        $categories = $query->get();

        if ($request->boolean('include_ads_count', true)) {
            $counts = $listingCounts->countsByCategoryId();
            $this->attachListingCounts($categories, $counts);
        }

        return ApiResponse::success(CategoryResource::collection($categories));
    }

    public function show(Category $category): JsonResponse
    {
        $category->load(['translations', 'children.translations']);

        return ApiResponse::success(new CategoryResource($category));
    }

    public function children(Category $category): JsonResponse
    {
        $children = Category::query()
            ->with([
                'translations',
                'children' => fn ($q) => $q->where('status', true)->orderBy('category_order'),
                'children.translations',
            ])
            ->withCount(['children as children_count' => fn ($q) => $q->where('status', true)])
            ->where('parent_id', $category->id)
            ->where('status', true)
            ->orderBy('category_order')
            ->get();

        return ApiResponse::success(CategoryResource::collection($children));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Category>  $categories
     * @param  array<int, int>  $counts
     */
    private function attachListingCounts($categories, array $counts): void
    {
        foreach ($categories as $category) {
            $category->setAttribute('ads_count', $counts[$category->id] ?? 0);

            if ($category->relationLoaded('children') && $category->children->isNotEmpty()) {
                $this->attachListingCounts($category->children, $counts);
            }
        }
    }
}
