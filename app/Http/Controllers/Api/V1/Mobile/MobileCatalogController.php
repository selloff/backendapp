<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\MobileCategoryResource;
use App\Http\Resources\Api\V1\Mobile\MobileProductResource;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Wishlist;
use App\Support\MobileResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileCatalogController extends Controller
{
    public function paginated(Request $request): JsonResponse
    {
        $perPage = min($request->integer('limit', $request->integer('per_page', 20)), 50);
        $page = max($request->integer('page', 1), 1);

        $query = $this->baseProductQuery($request);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginatedProductsResponse($paginator, 'Products fetched successfully.');
    }

    public function paginatedByCategorySlug(Request $request): JsonResponse
    {
        if (! $request->filled('slug')) {
            return MobileResponse::error('Missing slug parameter.', 422);
        }

        $request->merge(['category_slug' => $request->string('slug')->toString()]);

        return $this->paginated($request);
    }

    public function paginatedDeclutter(Request $request): JsonResponse
    {
        $perPage = min($request->integer('limit', $request->integer('per_page', 50)), 50);
        $page = max($request->integer('page', 1), 1);

        $query = $this->baseProductQuery($request)
            ->whereNotNull('price_discounted')
            ->whereColumn('price_discounted', '<', 'price');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginatedProductsResponse(
            $paginator,
            $paginator->total() > 0 ? 'Special Offer Products fetched successfully' : 'No Special offer products found',
        );
    }

    public function paginatedFreebies(Request $request): JsonResponse
    {
        $perPage = min($request->integer('limit', $request->integer('per_page', 50)), 50);
        $page = max($request->integer('page', 1), 1);

        $query = $this->baseProductQuery($request)
            ->where(function (Builder $q): void {
                $q->where('price', 0)
                    ->orWhere('price_discounted', 0);
            });

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginatedProductsResponse(
            $paginator,
            $paginator->total() > 0 ? 'Freebie Products fetched successfully' : 'No Freebie products found',
        );
    }

    public function paginatedFavourites(Request $request): JsonResponse
    {
        $perPage = min($request->integer('limit', $request->integer('per_page', 20)), 50);
        $page = max($request->integer('page', 1), 1);

        $query = $this->baseProductQuery($request)
            ->whereIn('id', Wishlist::query()
                ->where('user_id', $request->user()->id)
                ->select('product_id'));

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginatedProductsResponse(
            $paginator,
            $paginator->total() > 0 ? 'Favourite Listings fetched successfully.' : 'No Favourite listings found.',
        );
    }

    /**
     * @return Builder<Product>
     */
    private function baseProductQuery(Request $request): Builder
    {
        return Product::query()
            ->with(['translations', 'vendor.vendorProfile', 'category.translations', 'images'])
            ->where('status', 'published')
            ->where('visibility', 'visible')
            ->where('is_active', true)
            ->when($request->boolean('featured'), fn (Builder $q) => $q->where('is_promoted', true))
            ->when($request->filled('category_id'), fn (Builder $q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('category_slug'), function (Builder $q) use ($request) {
                $q->whereHas('category', fn (Builder $inner) => $inner->where('slug', $request->string('category_slug')));
            })
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->whereHas('translations', fn (Builder $inner) => $inner
                    ->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->orderByDesc('created_at');
    }

    private function paginatedProductsResponse($paginator, string $message): JsonResponse
    {
        return MobileResponse::success(
            MobileProductResource::collection($paginator->items())->resolve(),
            200,
            $message,
            [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        );
    }

    public function show(string $product): JsonResponse
    {
        $query = Product::query()
            ->with(['translations', 'vendor.vendorProfile', 'category.translations', 'images']);

        $model = is_numeric($product)
            ? $query->where('id', (int) $product)->firstOrFail()
            : $query->where('slug', $product)->firstOrFail();

        return MobileResponse::success(
            new MobileProductResource($model),
            200,
            'Product fetched successfully.',
        );
    }

    public function parentCategories(): JsonResponse
    {
        $categories = Category::query()
            ->with(['translations'])
            ->whereNull('parent_id')
            ->where('status', true)
            ->orderBy('category_order')
            ->get();

        return MobileResponse::success(
            MobileCategoryResource::collection($categories)->resolve(),
            200,
            'Categories fetched successfully.',
        );
    }
}
