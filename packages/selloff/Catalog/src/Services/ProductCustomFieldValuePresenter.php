<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;

class ProductCustomFieldValuePresenter
{
    /**
     * @return list<array{name: string, value: string, where_to_display: int}>
     */
    public function forProduct(Product $product): array
    {
        if (! $product->relationLoaded('customFieldProducts')) {
            return [];
        }

        /** @var array<int, array{name: string, value: string, where_to_display: int, field_type: string|null}> $grouped */
        $grouped = [];

        foreach ($product->customFieldProducts as $row) {
            $field = $row->customField;
            if ($field === null || ! $field->status) {
                continue;
            }

            $fieldId = (int) $field->id;
            if (! isset($grouped[$fieldId])) {
                $grouped[$fieldId] = [
                    'name' => $field->label ?? "Field {$fieldId}",
                    'value' => '',
                    'where_to_display' => (int) ($field->where_to_display ?? 2),
                    'field_type' => $field->field_type,
                ];
            }

            $fieldType = (string) ($field->field_type ?? '');
            if (in_array($fieldType, ['text', 'textarea', 'number', 'date'], true)) {
                $grouped[$fieldId]['value'] = trim((string) ($row->field_value ?? ''));

                continue;
            }

            $optionLabel = trim((string) ($row->option?->label ?? ''));
            if ($optionLabel === '') {
                continue;
            }

            if ($grouped[$fieldId]['value'] !== '') {
                $grouped[$fieldId]['value'] .= ', ';
            }

            $grouped[$fieldId]['value'] .= $optionLabel;
        }

        return array_values(array_filter(
            $grouped,
            static fn (array $item): bool => $item['value'] !== '',
        ));
    }
}
