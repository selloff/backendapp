<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Review\Http\Resources\Api\V1\ProductReviewResource;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $vendorId = $request->user()->id;
        $productIds = Product::query()->where('vendor_id', $vendorId)->pluck('id');

        $reviews = ProductReview::query()
            ->with(['user', 'product.translations'])
            ->whereIn('product_id', $productIds)
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 100));

        $reviews->through(fn (ProductReview $review) => new ProductReviewResource($review));

        return ApiResponse::success($reviews);
    }
}
