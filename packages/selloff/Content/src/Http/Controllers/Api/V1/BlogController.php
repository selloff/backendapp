<?php

namespace App\Modules\Selloff\Content\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Content\Models\BlogCategory;
use App\Modules\Selloff\Content\Models\BlogComment;
use App\Modules\Selloff\Content\Models\BlogPost;
use App\Modules\Selloff\Content\Models\BlogTag;
use App\Services\Platform\PlatformSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = BlogPost::query()
            ->with('categories')
            ->where('is_published', true)
            ->when($request->filled('category_id'), function ($query) use ($request): void {
                $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->where(
                    'blog_categories.id',
                    $request->integer('category_id'),
                ));
            })
            ->orderByDesc('published_at')
            ->paginate(min($request->integer('per_page', 12), 50));

        return ApiResponse::success($posts);
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::success(
            BlogCategory::query()->orderBy('name')->get(['id', 'name', 'slug']),
        );
    }

    public function show(string $slug): JsonResponse
    {
        $post = BlogPost::query()
            ->with(['categories', 'tags'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return ApiResponse::success($post);
    }

    public function comments(Request $request, string $slug, PlatformSettingsService $settings): JsonResponse
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $comments = BlogComment::query()
            ->with('user:id,name,first_name,last_name')
            ->where('blog_post_id', $post->id)
            ->where('status', 'approved')
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', 20), 50));

        $platform = $settings->all();

        return ApiResponse::success([
            'comments_enabled' => filter_var($platform['blog_comments_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'comments' => $comments,
        ]);
    }

    public function storeComment(Request $request, string $slug, PlatformSettingsService $settings): JsonResponse
    {
        $platform = $settings->all();
        if (! filter_var($platform['blog_comments_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return ApiResponse::error('Blog comments are disabled.', 403);
        }

        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $comment = BlogComment::query()->create([
            'blog_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'comment' => $data['comment'],
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        return ApiResponse::success(
            $comment->load('user:id,name,first_name,last_name'),
            message: 'Comment submitted for review.',
        );
    }

    public function tag(Request $request, string $tagSlug): JsonResponse
    {
        $posts = BlogPost::query()
            ->with('categories')
            ->where('is_published', true)
            ->whereHas('tags', fn ($q) => $q->where('tag_slug', $tagSlug))
            ->orderByDesc('published_at')
            ->paginate(min($request->integer('per_page', 12), 50));

        $tag = BlogTag::query()->where('tag_slug', $tagSlug)->first();

        return ApiResponse::success([
            'tag' => $tag ? ['tag' => $tag->tag, 'tag_slug' => $tag->tag_slug] : ['tag' => str_replace('-', ' ', $tagSlug), 'tag_slug' => $tagSlug],
            'posts' => $posts,
        ]);
    }
}
