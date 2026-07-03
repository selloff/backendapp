<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CustomFieldController extends Controller
{
    public function byCategory(int $categoryId): JsonResponse
    {
        $category = Category::query()->findOrFail($categoryId);
        $categoryIds = $this->ancestorCategoryIds($category);

        $fields = CustomField::query()
            ->with('options')
            ->where('status', true)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->orderBy('field_order')
            ->get()
            ->unique('id')
            ->values()
            ->map(fn (CustomField $field) => [
                'id' => $field->id,
                'field_type' => $field->field_type,
                'label' => $field->label ?? "Field {$field->id}",
                'is_required' => (bool) $field->is_required,
                'field_options' => $field->options->map(fn ($option) => [
                    'id' => $option->id,
                    'option_key' => $option->option_key,
                    'label' => $option->label ?? $option->option_key,
                ])->values(),
            ]);

        if ($fields->isEmpty()) {
            return ApiResponse::success([], 200, 'No custom fields found.');
        }

        return ApiResponse::success($fields);
    }

    /** @return list<int> */
    private function ancestorCategoryIds(Category $category): array
    {
        $ids = [];
        $current = $category;

        while ($current) {
            $ids[] = $current->id;

            if (! $current->parent_id) {
                break;
            }

            $current = Category::query()->find($current->parent_id);

            if (! $current) {
                break;
            }
        }

        return $ids;
    }

    public function listingFields(int $categoryId): JsonResponse
    {
        return $this->byCategory($categoryId);
    }
}
