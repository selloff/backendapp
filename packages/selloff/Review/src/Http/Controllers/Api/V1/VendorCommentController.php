<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Review\Http\Resources\Api\V1\ProductCommentResource;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorCommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendorId = $request->user()->id;

        $comments = ProductComment::query()
            ->with(['product:id,slug', 'product.translations', 'user:id,email,first_name,last_name'])
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->when($request->has('approved'), function ($query) use ($request): void {
                $query->where('is_approved', $request->boolean('approved'));
            })
            ->orderByDesc('id')
            ->paginate(20);

        $comments->through(fn (ProductComment $comment) => new ProductCommentResource($comment));

        return ApiResponse::success($comments);
    }

    public function update(Request $request, ProductComment $comment): JsonResponse
    {
        $comment->load('product');
        abort_unless($comment->product?->vendor_id === $request->user()->id, 403);

        $data = $request->validate([
            'is_approved' => ['sometimes', 'boolean'],
            'vendor_reply' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $comment->update($data);

        return ApiResponse::success(new ProductCommentResource($comment->fresh()->load(['product', 'user'])));
    }
}
