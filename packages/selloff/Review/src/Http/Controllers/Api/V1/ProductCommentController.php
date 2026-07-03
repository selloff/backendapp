<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Review\Http\Requests\Api\V1\StoreProductCommentRequest;
use App\Modules\Selloff\Review\Http\Resources\Api\V1\ProductCommentResource;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCommentController extends Controller
{
    public function index(Request $request, Product $product): JsonResponse
    {
        $comments = ProductComment::query()
            ->with('user:id,name')
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->whereNull('parent_id')
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 50));

        $comments->through(fn (ProductComment $comment) => new ProductCommentResource($comment));

        return ApiResponse::success($comments);
    }

    public function store(StoreProductCommentRequest $request, Product $product): JsonResponse
    {
        $comment = ProductComment::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'comment' => $request->string('comment')->toString(),
            'is_approved' => false,
        ]);

        return ApiResponse::success(
            new ProductCommentResource($comment->load('user:id,name')),
            201,
            'Comment submitted and awaiting moderation.',
        );
    }
}
