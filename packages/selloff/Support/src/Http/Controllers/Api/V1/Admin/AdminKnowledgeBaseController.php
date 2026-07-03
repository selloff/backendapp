<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Http\Resources\Api\V1\KnowledgeBaseArticleResource;
use App\Modules\Selloff\Support\Models\KnowledgeBaseArticle;
use App\Modules\Selloff\Support\Models\KnowledgeBaseCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminKnowledgeBaseController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = KnowledgeBaseCategory::query()
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category = KnowledgeBaseCategory::query()->create([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ApiResponse::success($category, 201);
    }

    public function updateCategory(Request $request, KnowledgeBaseCategory $knowledgeBaseCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $knowledgeBaseCategory->update($data);

        return ApiResponse::success($knowledgeBaseCategory->fresh());
    }

    public function destroyCategory(KnowledgeBaseCategory $knowledgeBaseCategory): JsonResponse
    {
        $knowledgeBaseCategory->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function articles(Request $request): JsonResponse
    {
        $articles = KnowledgeBaseArticle::query()
            ->with('category')
            ->when($request->filled('category_id'), fn ($q) => $q->where('knowledge_base_category_id', $request->integer('category_id')))
            ->orderByDesc('id')
            ->paginate(20);

        $articles->through(fn (KnowledgeBaseArticle $article) => new KnowledgeBaseArticleResource($article));

        return ApiResponse::success($articles);
    }

    public function storeArticle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'knowledge_base_category_id' => ['nullable', 'integer', 'exists:knowledge_base_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:knowledge_base_articles,slug'],
            'content' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $article = KnowledgeBaseArticle::query()->create([
            'knowledge_base_category_id' => $data['knowledge_base_category_id'] ?? null,
            'title' => $data['title'],
            'slug' => $data['slug'] ?? Str::slug($data['title']),
            'content' => $data['content'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return ApiResponse::success(new KnowledgeBaseArticleResource($article->load('category')), 201);
    }

    public function updateArticle(Request $request, KnowledgeBaseArticle $knowledgeBaseArticle): JsonResponse
    {
        $data = $request->validate([
            'knowledge_base_category_id' => ['nullable', 'integer', 'exists:knowledge_base_categories,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:knowledge_base_articles,slug,'.$knowledgeBaseArticle->id],
            'content' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $knowledgeBaseArticle->update($data);

        return ApiResponse::success(new KnowledgeBaseArticleResource($knowledgeBaseArticle->fresh()->load('category')));
    }

    public function destroyArticle(KnowledgeBaseArticle $knowledgeBaseArticle): JsonResponse
    {
        $knowledgeBaseArticle->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
