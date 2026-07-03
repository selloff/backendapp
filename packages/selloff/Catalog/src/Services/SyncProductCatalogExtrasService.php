<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\DigitalFile;
use App\Modules\Selloff\Catalog\Models\CustomField;
use App\Modules\Selloff\Catalog\Models\CustomFieldProduct;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductLicenseKey;
use App\Modules\Selloff\Catalog\Models\ProductOption;
use App\Modules\Selloff\Catalog\Models\ProductOptionValue;
use App\Modules\Selloff\Catalog\Models\Tag;

class SyncProductCatalogExtrasService
{
    /**
     * @param  array<int, array{name: string, values?: list<string>}>|null  $options
     * @param  array<int, array{file_name: string, storage?: string}>|null  $digitalFiles
     * @param  list<string>|null  $licenseKeys
     * @param  list<string>|null  $tags
     * @param  array<int, array{custom_field_id: int, field_value?: string|null, custom_field_option_id?: int|null}>|null  $customFields
     */
    public function sync(
        Product $product,
        ?array $options = null,
        ?array $digitalFiles = null,
        ?array $licenseKeys = null,
        ?int $userId = null,
        ?array $tags = null,
        ?array $customFields = null,
    ): void {
        if ($options !== null) {
            $this->syncOptions($product, $options);
        }

        if ($digitalFiles !== null) {
            $this->syncDigitalFiles($product, $digitalFiles, $userId);
        }

        if ($licenseKeys !== null) {
            $this->appendLicenseKeys($product, $licenseKeys);
        }

        if ($tags !== null) {
            $this->syncTags($product, $tags);
        }

        if ($customFields !== null) {
            $this->syncCustomFields($product, $customFields);
        }
    }

    /** @param  array<int, array{name: string, values?: list<string>}>  $options */
    private function syncOptions(Product $product, array $options): void
    {
        ProductOption::query()->where('product_id', $product->id)->delete();

        foreach (array_values($options) as $index => $option) {
            if (empty($option['name'])) {
                continue;
            }

            $created = ProductOption::query()->create([
                'product_id' => $product->id,
                'name' => $option['name'],
                'sort_order' => $index,
            ]);

            foreach (array_values($option['values'] ?? []) as $valueIndex => $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                ProductOptionValue::query()->create([
                    'product_option_id' => $created->id,
                    'value' => $value,
                    'sort_order' => $valueIndex,
                ]);
            }
        }
    }

    /** @param  array<int, array{file_name: string, storage?: string}>  $digitalFiles */
    private function syncDigitalFiles(Product $product, array $digitalFiles, ?int $userId): void
    {
        DigitalFile::query()->where('product_id', $product->id)->delete();

        foreach ($digitalFiles as $file) {
            if (empty($file['file_name'])) {
                continue;
            }

            DigitalFile::query()->create([
                'product_id' => $product->id,
                'user_id' => $userId,
                'file_name' => $file['file_name'],
                'storage' => $file['storage'] ?? 'public',
            ]);
        }
    }

    /** @param  list<string>  $licenseKeys */
    private function appendLicenseKeys(Product $product, array $licenseKeys): void
    {
        foreach ($licenseKeys as $key) {
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            ProductLicenseKey::query()->firstOrCreate(
                ['product_id' => $product->id, 'license_key' => $key],
                ['is_used' => false],
            );
        }
    }

    /** @param  list<string>  $tags */
    private function syncTags(Product $product, array $tags): void
    {
        $tagIds = [];

        foreach ($tags as $tagName) {
            $tagName = trim(mb_strtolower((string) $tagName));
            if ($tagName === '') {
                continue;
            }

            $tag = Tag::query()->firstOrCreate(
                ['tag' => $tagName, 'lang_id' => 1],
                ['lang_id' => 1],
            );
            $tagIds[] = $tag->id;
        }

        $product->tags()->sync($tagIds);
    }

    /** @param  array<int, array{custom_field_id: int, field_value?: string|null, custom_field_option_id?: int|null}>  $fields */
    private function syncCustomFields(Product $product, array $fields): void
    {
        CustomFieldProduct::query()->where('product_id', $product->id)->delete();

        $fieldIds = array_values(array_filter(array_map(
            fn (array $field): int => (int) ($field['custom_field_id'] ?? 0),
            $fields,
        ), fn (int $id): bool => $id > 0));

        $filterKeys = CustomField::query()
            ->whereIn('id', $fieldIds)
            ->pluck('product_filter_key', 'id');

        foreach ($fields as $field) {
            $fieldId = (int) ($field['custom_field_id'] ?? 0);
            if ($fieldId <= 0) {
                continue;
            }

            $value = trim((string) ($field['field_value'] ?? ''));
            $optionId = isset($field['custom_field_option_id']) ? (int) $field['custom_field_option_id'] : null;

            if ($value === '' && ($optionId === null || $optionId <= 0)) {
                continue;
            }

            CustomFieldProduct::query()->create([
                'product_id' => $product->id,
                'custom_field_id' => $fieldId,
                'field_value' => $value !== '' ? $value : null,
                'custom_field_option_id' => $optionId > 0 ? $optionId : null,
                'product_filter_key' => $filterKeys[$fieldId] ?? null,
            ]);
        }
    }
}
