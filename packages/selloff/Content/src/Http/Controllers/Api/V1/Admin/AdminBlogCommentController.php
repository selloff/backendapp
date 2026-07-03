<?php

namespace App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Content\Models\BlogComment;
use App\Modules\Selloff\Content\Services\AdminBlogCommentsListService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBlogCommentController extends Controller
{
    public function __construct(
        private readonly AdminBlogCommentsListService $comments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->comments->paginate($request));
    }

    public function update(Request $request, BlogComment $blogComment): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:approved,pending,rejected'],
        ]);

        $blogComment->update(['status' => $data['status']]);

        return ApiResponse::success($blogComment->fresh()->load(['post:id,title,slug', 'user:id,first_name,last_name,email']));
    }

    public function destroy(BlogComment $blogComment): JsonResponse
    {
        $blogComment->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:blog_comments,id'],
            'action' => ['required', 'in:approve,delete'],
        ]);

        $query = BlogComment::query()->whereIn('id', $data['ids']);

        if ($data['action'] === 'delete') {
            $count = $query->delete();

            return ApiResponse::success(['deleted' => $count]);
        }

        $count = $query->update(['status' => 'approved']);

        return ApiResponse::success(['approved' => $count]);
    }
}
