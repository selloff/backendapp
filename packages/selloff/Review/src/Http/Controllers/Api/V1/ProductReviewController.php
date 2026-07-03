<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Review\Http\Requests\Api\V1\StoreProductReviewRequest;
use App\Modules\Selloff\Review\Http\Resources\Api\V1\ProductReviewResource;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductReviewController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        $reviews = ProductReview::query()
            ->with('user')
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->latest()
            ->paginate(15);

        $reviews->through(fn (ProductReview $review) => new ProductReviewResource($review));

        return ApiResponse::success($reviews);
    }

    public function store(StoreProductReviewRequest $request, Product $product): JsonResponse
    {
        $review = ProductReview::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
            ],
            [
                'rating' => $request->integer('rating'),
                'review' => $request->input('review'),
                'is_approved' => true,
            ],
        );

        return ApiResponse::success(new ProductReviewResource($review->load('user')), 201);
    }
}
