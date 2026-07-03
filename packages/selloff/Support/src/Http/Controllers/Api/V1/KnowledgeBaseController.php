<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Http\Resources\Api\V1\KnowledgeBaseArticleResource;
use App\Modules\Selloff\Support\Models\KnowledgeBaseArticle;
use App\Modules\Selloff\Support\Models\KnowledgeBaseCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class KnowledgeBaseController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = KnowledgeBaseCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['articles' => fn ($q) => $q->where('is_active', true)->orderBy('title')])
            ->get()
            ->map(fn (KnowledgeBaseCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'articles' => KnowledgeBaseArticleResource::collection($category->articles),
            ]);

        return ApiResponse::success($categories);
    }

    public function show(string $slug): JsonResponse
    {
        $article = KnowledgeBaseArticle::query()
            ->with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return ApiResponse::success(new KnowledgeBaseArticleResource($article));
    }
}
