<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\BrandResource;
use App\Modules\Selloff\Catalog\Models\Brand;
use App\Modules\Selloff\Catalog\Services\BrandSettingsService;
use App\Modules\Selloff\Catalog\Services\CategoryPathService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        private readonly BrandSettingsService $brandSettings,
        private readonly CategoryPathService $categoryPaths,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $this->brandSettings->isEnabled()) {
            return ApiResponse::success([]);
        }

        $query = Brand::query()->orderBy('name');

        $categoryId = $request->integer('category_id');
        if ($categoryId > 0) {
            $categoryIds = $this->categoryPaths->ancestorIdsIncludingSelf($categoryId);
            if ($categoryIds !== []) {
                $query->whereHas('categories', fn ($relation) => $relation->whereIn('categories.id', $categoryIds));
            }
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereLike('name', '%'.$search.'%', caseSensitive: false);
        }

        return ApiResponse::success(BrandResource::collection($query->get()));
    }
}
