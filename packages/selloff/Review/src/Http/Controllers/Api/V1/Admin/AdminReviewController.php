<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Review\Http\Resources\Api\V1\ProductReviewResource;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 15)), 100);
        $search = $request->string('q')->trim();

        $reviews = ProductReview::query()
            ->with(['user', 'product.translations'])
            ->when($request->has('approved'), fn ($q) => $q->where('is_approved', $request->boolean('approved')))
            ->when($search->isNotEmpty(), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('review', 'ilike', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('email', 'ilike', '%'.$search.'%')
                                ->orWhere('first_name', 'ilike', '%'.$search.'%')
                                ->orWhere('last_name', 'ilike', '%'.$search.'%');
                        })
                        ->orWhereHas('product.translations', function ($productQuery) use ($search): void {
                            $productQuery->where('title', 'ilike', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        $reviews->through(fn (ProductReview $review) => new ProductReviewResource($review));

        return ApiResponse::success($reviews);
    }

    public function update(Request $request, ProductReview $review): JsonResponse
    {
        $data = $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);

        $review->update($data);

        return ApiResponse::success(new ProductReviewResource($review->fresh()->load(['user', 'product.translations'])));
    }

    public function destroy(ProductReview $review): JsonResponse
    {
        $review->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:product_reviews,id'],
        ]);

        $count = ProductReview::query()->whereIn('id', $data['ids'])->delete();

        return ApiResponse::success(['deleted' => $count]);
    }
}
