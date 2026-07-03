<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Wishlist;
use App\Modules\Selloff\Review\Services\WishlistService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(
        private readonly WishlistService $wishlistService,
    ) {}

    public function guestPreview(Request $request): JsonResponse
    {
        $productIds = $request->input('product_ids', []);
        if (! is_array($productIds)) {
            $productIds = [];
        }

        $products = $this->wishlistService->guestPreviewProducts($productIds);

        return ApiResponse::success([
            'items' => $products->map(fn (Product $product) => [
                'id' => 0,
                'product' => new ProductResource($product),
                'created_at' => null,
            ])->values(),
        ]);
    }

    public function mergeGuest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $result = $this->wishlistService->mergeGuestProducts(
            $request->user(),
            $data['product_ids'],
        );

        return ApiResponse::success($result, 200, 'Guest wishlist merged.');
    }

    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::query()
            ->with(['product.translations', 'product.vendor.vendorProfile'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return ApiResponse::success([
            'items' => $items->map(fn (Wishlist $item) => [
                'id' => $item->id,
                'product' => new ProductResource($item->product),
                'created_at' => $item->created_at,
            ]),
        ]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $wishlist = Wishlist::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return ApiResponse::success([
            'id' => $wishlist->id,
            'product_id' => $product->id,
        ], 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->delete();

        return ApiResponse::success(message: 'Removed from wishlist.');
    }
}
