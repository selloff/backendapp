<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CategoryTranslation;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoryBulkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1', 'max:50'],
            'categories.*.name' => ['required', 'string', 'max:255'],
            'categories.*.parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'categories.*.slug' => ['nullable', 'string', 'max:255'],
        ]);

        $created = [];
        $errors = [];

        foreach ($data['categories'] as $index => $row) {
            try {
                $slug = $row['slug'] ?? Str::slug($row['name']);
                $suffix = 1;
                while (Category::query()->where('slug', $slug)->exists()) {
                    $slug = Str::slug($row['name']).'-'.$suffix;
                    $suffix++;
                }

                $category = Category::query()->create([
                    'parent_id' => $row['parent_id'] ?? null,
                    'slug' => $slug,
                    'status' => true,
                ]);

                CategoryTranslation::query()->create([
                    'category_id' => $category->id,
                    'locale' => 'en',
                    'name' => $row['name'],
                ]);

                $created[] = ['index' => $index, 'id' => $category->id, 'slug' => $category->slug];
            } catch (\Throwable $exception) {
                $errors[] = ['index' => $index, 'message' => $exception->getMessage()];
            }
        }

        return ApiResponse::success([
            'created_count' => count($created),
            'created' => $created,
            'errors' => $errors,
        ], count($created) > 0 ? 201 : 422);
    }
}
