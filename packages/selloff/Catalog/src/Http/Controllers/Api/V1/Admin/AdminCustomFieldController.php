<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\CustomFieldResource;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldOption;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCustomFieldController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $query = CustomField::query()
            ->with(['options', 'categories'])
            ->orderBy('field_order')
            ->orderBy('id');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->whereLike('label', '%'.$search.'%', caseSensitive: false);
        }

        $paginated = $query->paginate($perPage);

        return ApiResponse::success([
            'data' => CustomFieldResource::collection($paginated->items())->resolve(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    public function show(CustomField $customField): JsonResponse
    {
        $customField->load(['options', 'categories']);

        return ApiResponse::success(new CustomFieldResource($customField));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field_type' => ['required', 'string', 'max:30'],
            'label' => ['required', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
            'field_order' => ['nullable', 'integer', 'min:0'],
            'is_product_filter' => ['nullable', 'boolean'],
            'product_filter_key' => ['nullable', 'string', 'max:255'],
            'where_to_display' => ['nullable', 'integer', Rule::in([1, 2])],
            'sort_options' => ['nullable', 'string', Rule::in(['date', 'date_desc', 'alphabetically'])],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $field = CustomField::query()->create([
            'field_type' => $data['field_type'],
            'label' => $data['label'],
            'is_required' => $data['is_required'] ?? false,
            'status' => $data['status'] ?? true,
            'field_order' => $data['field_order'] ?? 1,
            'is_product_filter' => $data['is_product_filter'] ?? false,
            'product_filter_key' => $data['product_filter_key'] ?? Str::slug($data['label'], '_'),
            'where_to_display' => $data['where_to_display'] ?? 2,
            'sort_options' => $data['sort_options'] ?? 'alphabetically',
        ]);

        if (! empty($data['category_ids'])) {
            $field->categories()->sync($data['category_ids']);
        }

        $field->load(['options', 'categories']);

        return ApiResponse::success(new CustomFieldResource($field), 201);
    }

    public function update(Request $request, CustomField $customField): JsonResponse
    {
        $data = $request->validate([
            'field_type' => ['sometimes', 'string', 'max:30'],
            'label' => ['sometimes', 'string', 'max:255'],
            'is_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'boolean'],
            'field_order' => ['sometimes', 'integer', 'min:0'],
            'is_product_filter' => ['sometimes', 'boolean'],
            'product_filter_key' => ['nullable', 'string', 'max:255'],
            'where_to_display' => ['sometimes', 'integer', Rule::in([1, 2])],
            'sort_options' => ['sometimes', 'string', Rule::in(['date', 'date_desc', 'alphabetically'])],
        ]);

        $customField->update($data);
        $customField->load(['options', 'categories']);

        return ApiResponse::success(new CustomFieldResource($customField));
    }

    public function destroy(CustomField $customField): JsonResponse
    {
        $customField->delete();

        return ApiResponse::success(['deleted' => true]);
    }

    public function syncCategories(Request $request, CustomField $customField): JsonResponse
    {
        $data = $request->validate([
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $customField->categories()->sync($data['category_ids']);
        $customField->load(['options', 'categories']);

        return ApiResponse::success(new CustomFieldResource($customField));
    }

    public function attachCategory(Request $request, CustomField $customField): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $customField->categories()->syncWithoutDetaching([$data['category_id']]);
        $customField->load(['options', 'categories']);

        return ApiResponse::success(new CustomFieldResource($customField));
    }

    public function detachCategory(CustomField $customField, Category $category): JsonResponse
    {
        $customField->categories()->detach($category->id);
        $customField->load(['options', 'categories']);

        return ApiResponse::success(new CustomFieldResource($customField));
    }

    public function toggleProductFilter(CustomField $customField): JsonResponse
    {
        if (! in_array($customField->field_type, ['single_select', 'multi_select'], true)) {
            return ApiResponse::error('Only select fields can be used as product filters.', 422);
        }

        $customField->update(['is_product_filter' => ! $customField->is_product_filter]);
        $customField->load(['options', 'categories']);

        return ApiResponse::success(new CustomFieldResource($customField));
    }

    public function storeOption(Request $request, CustomField $customField): JsonResponse
    {
        $data = $request->validate([
            'option_key' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $option = CustomFieldOption::query()->create([
            'custom_field_id' => $customField->id,
            'option_key' => $data['option_key'],
            'label' => $data['label'] ?? $data['option_key'],
        ]);

        return ApiResponse::success($option, 201);
    }

    public function updateOption(Request $request, CustomField $customField, CustomFieldOption $option): JsonResponse
    {
        abort_unless($option->custom_field_id === $customField->id, 404);

        $data = $request->validate([
            'option_key' => ['sometimes', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $option->update($data);

        return ApiResponse::success($option);
    }

    public function destroyOption(CustomField $customField, CustomFieldOption $option): JsonResponse
    {
        abort_unless($option->custom_field_id === $customField->id, 404);

        $option->delete();

        return ApiResponse::success(['deleted' => true]);
    }
}
