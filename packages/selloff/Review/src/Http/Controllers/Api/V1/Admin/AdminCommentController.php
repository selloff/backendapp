<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Review\Models\ProductComment;
use App\Modules\Selloff\Review\Services\AdminCommentsListService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCommentController extends Controller
{
    public function __construct(
        private readonly AdminCommentsListService $comments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->comments->paginate($request));
    }

    public function update(Request $request, ProductComment $comment): JsonResponse
    {
        $data = $request->validate([
            'is_approved' => ['required', 'boolean'],
        ]);

        $comment->update(['is_approved' => $data['is_approved']]);

        return ApiResponse::success($comment->fresh()->load([
            'product:id,slug',
            'product.translations:id,product_id,locale,title',
            'user:id,email,first_name,last_name,username',
        ]));
    }

    public function destroy(ProductComment $comment): JsonResponse
    {
        $comment->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:comments,id'],
            'action' => ['required', 'in:approve,delete'],
        ]);

        $query = ProductComment::query()->whereIn('id', $data['ids']);

        if ($data['action'] === 'delete') {
            $count = $query->delete();

            return ApiResponse::success(['deleted' => $count]);
        }

        $count = $query->update(['is_approved' => true]);

        return ApiResponse::success(['approved' => $count]);
    }
}
