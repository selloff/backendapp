<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\DuplicateVendorProductService;
use App\Modules\Selloff\Catalog\Services\MarkVendorProductSoldService;
use App\Modules\Selloff\Affiliate\Services\VendorAffiliateProgramService;
use App\Modules\Selloff\Catalog\Services\VendorProductListingStatsService;
use App\Modules\Selloff\User\Models\ReferralProfile;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorProductController extends Controller
{
    public function __construct(
        private readonly VendorAffiliateProgramService $affiliateProgram,
        private readonly VendorProductListingStatsService $listingStats,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $search = $request->filled('search')
            ? $request->string('search')
            : ($request->filled('q') ? $request->string('q') : null);

        $listType = $request->filled('st')
            ? $request->string('st')
            : ($request->filled('list') ? $request->string('list') : 'active');

        $categoryId = $request->filled('subcategory')
            ? $request->integer('subcategory')
            : ($request->filled('category') ? $request->integer('category') : null);

        $query = Product::query()
            ->with(['translations', 'category.translations', 'brand', 'images'])
            ->where('vendor_id', $request->user()->id);

        $this->applyListTypeFilter($query, (string) $listType);

        $query
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('listing_type'), fn (Builder $q) => $q->where('listing_type', $request->string('listing_type')))
            ->when($request->filled('product_type'), fn (Builder $q) => $q->where('type', $request->string('product_type')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->string('type')))
            ->when($categoryId, function (Builder $q) use ($categoryId) {
                $categoryIds = $this->categoryTreeIds($categoryId);
                $q->whereIn('category_id', $categoryIds);
            })
            ->when($request->string('stock') === 'in_stock', function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->where('type', '!=', 'physical')->orWhere('stock', '>', 0);
                });
            })
            ->when($request->string('stock') === 'out_of_stock', fn (Builder $q) => $q->where('type', 'physical')->where('stock', '<=', 0))
            ->when($search, function (Builder $q) use ($search) {
                $term = '%'.$search.'%';
                $q->where(function (Builder $inner) use ($term) {
                    $inner->whereHas('translations', fn (Builder $translation) => $translation->where('title', 'like', $term))
                        ->orWhere('sku', 'like', $term);
                });
            })
            ->orderByDesc('id');

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));

        $products = $paginator->getCollection();
        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $lifetimePageviews = $products->mapWithKeys(fn (Product $product) => [
            $product->id => (int) ($product->pageviews ?? 0),
        ])->all();
        $statsByProduct = $this->listingStats->lifetimeForProducts($productIds, $lifetimePageviews);

        $paginator->through(function (Product $product) use ($statsByProduct) {
            $product->setAttribute('listing_stats', $statsByProduct[$product->id] ?? [
                'impressions' => 0,
                'pageviews' => 0,
                'phone_views' => 0,
                'chats' => 0,
            ]);

            return new ProductResource($product);
        });

        return ApiResponse::success($paginator);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);
        abort_unless((int) $product->vendor_id === (int) $request->user()->id, 403);

        $product->load([
            'translations',
            'category.translations',
            'brand',
            'images',
            'options.values',
            'variants.optionValues',
            'digitalFiles',
            'licenseKeys',
            'tags',
            'customFieldProducts',
            'country',
            'state',
            'city',
        ])->loadCount('options');

        return ApiResponse::success(new ProductResource($product));
    }

    public function duplicate(Request $request, Product $product, DuplicateVendorProductService $duplicator): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        return ApiResponse::success($duplicator->duplicate($product, $request->user()), 201);
    }

    public function markSold(Request $request, Product $product, MarkVendorProductSoldService $markSold): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        return ApiResponse::success($markSold->markSold($product, $request->user()));
    }

    public function toggleAffiliate(Request $request, Product $product): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);
        abort_unless((int) $product->vendor_id === (int) $request->user()->id, 403);

        $profile = ReferralProfile::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['referral_code' => strtoupper(\Illuminate\Support\Str::random(8))],
        );

        abort_unless(
            $this->affiliateProgram->canManageProductAffiliate($profile),
            422,
            'Affiliate program is not enabled for selected products.',
        );

        $product->update(['is_affiliate' => ! $product->is_affiliate]);

        $product->load(['translations', 'category.translations', 'brand', 'images']);

        return ApiResponse::success(new ProductResource($product->fresh()));
    }

    private function applyListTypeFilter(Builder $query, string $listType): void
    {
        match ($listType) {
            'pending' => $query->vendorPendingItems(),
            'draft' => $query->vendorDraftItems(),
            'hidden' => $query->vendorHiddenItems(),
            'sold' => $query->vendorSoldItems(),
            default => $query->vendorItemsForSale(),
        };
    }

    /** @return list<int> */
    private function categoryTreeIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = Category::query()->where('parent_id', $categoryId)->pluck('id')->all();

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->categoryTreeIds((int) $childId));
        }

        return array_values(array_unique($ids));
    }
}
