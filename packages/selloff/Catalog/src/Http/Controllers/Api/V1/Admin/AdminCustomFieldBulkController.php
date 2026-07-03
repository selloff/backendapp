<?php

namespace App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldOption;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCustomFieldBulkController extends Controller
{
    /** @var list<string> */
    private const FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'single_select', 'multi_select'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'custom_fields' => ['required', 'array', 'min:1', 'max:50'],
            'custom_fields.*.label' => ['required', 'string', 'max:255'],
            'custom_fields.*.field_type' => ['required', 'string', Rule::in(self::FIELD_TYPES)],
            'custom_fields.*.is_required' => ['nullable', 'boolean'],
            'custom_fields.*.status' => ['nullable', 'boolean'],
            'custom_fields.*.field_order' => ['nullable', 'integer', 'min:0'],
            'custom_fields.*.is_product_filter' => ['nullable', 'boolean'],
            'custom_fields.*.category_ids' => ['nullable', 'array'],
            'custom_fields.*.category_ids.*' => ['integer', 'exists:categories,id'],
            'custom_fields.*.options' => ['nullable', 'array'],
            'custom_fields.*.options.*' => ['string', 'max:255'],
        ]);

        $created = [];
        $errors = [];

        foreach ($data['custom_fields'] as $index => $row) {
            try {
                $fieldType = (string) $row['field_type'];
                $options = array_values(array_filter(
                    array_map('trim', $row['options'] ?? []),
                    fn (string $value) => $value !== '',
                ));

                if (in_array($fieldType, ['single_select', 'multi_select'], true) && $options === []) {
                    throw new \InvalidArgumentException('Select fields require at least one option.');
                }

                if (($row['is_product_filter'] ?? false) && ! in_array($fieldType, ['single_select', 'multi_select'], true)) {
                    throw new \InvalidArgumentException('Only select fields can be product filters.');
                }

                $fieldId = DB::transaction(function () use ($row, $fieldType, $options): int {
                    $filterKey = $this->uniqueFilterKey(Str::slug((string) $row['label'], '_'));

                    $field = CustomField::query()->create([
                        'field_type' => $fieldType,
                        'label' => $row['label'],
                        'is_required' => $row['is_required'] ?? true,
                        'status' => $row['status'] ?? true,
                        'field_order' => $row['field_order'] ?? 1,
                        'is_product_filter' => $row['is_product_filter'] ?? false,
                        'product_filter_key' => $filterKey,
                        'where_to_display' => 2,
                        'sort_options' => 'alphabetically',
                    ]);

                    if (! empty($row['category_ids'])) {
                        $field->categories()->sync($row['category_ids']);
                    }

                    foreach ($options as $optionLabel) {
                        $optionKey = Str::slug($optionLabel, '_') ?: 'option';
                        CustomFieldOption::query()->create([
                            'custom_field_id' => $field->id,
                            'option_key' => $optionKey,
                            'label' => $optionLabel,
                        ]);
                    }

                    return $field->id;
                });

                $created[] = ['index' => $index, 'id' => $fieldId];
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

    private function uniqueFilterKey(string $base): string
    {
        $key = $base !== '' ? $base : 'field';
        $suffix = 1;

        while (CustomField::query()->where('product_filter_key', $key)->exists()) {
            $key = $base.'_'.$suffix;
            $suffix++;
        }

        return $key;
    }
}
