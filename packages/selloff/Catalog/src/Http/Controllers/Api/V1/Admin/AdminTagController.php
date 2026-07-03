<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Tag;
use App\Modules\Selloff\Catalog\Services\AdminTagsListService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTagController extends Controller
{
    public function __construct(
        private readonly AdminTagsListService $tags,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->tags->paginate($request));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(AdminTagsListService::validationRules());

        $tag = $this->tags->create([
            'tag' => trim($data['tag']),
            'lang_id' => (int) $data['lang_id'],
        ]);

        return ApiResponse::success($tag, 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        $data = $request->validate(AdminTagsListService::validationRules($tag));

        $updated = $this->tags->update($tag, [
            'tag' => trim($data['tag']),
            'lang_id' => (int) $data['lang_id'],
        ]);

        return ApiResponse::success($updated);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
