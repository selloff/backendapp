<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\CustomField;
use Illuminate\Support\Collection;

class ProductFilterFieldResolver
{
    public function __construct(
        private readonly CategoryPathService $categoryPaths,
        private readonly BrandSettingsService $brandSettings,
    ) {}

    /**
     * @return list<string>
     */
    public function knownFilterKeys(): array
    {
        $keys = CustomField::query()
            ->where('status', true)
            ->where('is_product_filter', true)
            ->whereIn('field_type', ['single_select', 'multi_select'])
            ->whereNotNull('product_filter_key')
            ->pluck('product_filter_key')
            ->map(fn ($key) => (string) $key)
            ->unique()
            ->values()
            ->all();

        if ($this->brandSettings->isEnabled()) {
            array_unshift($keys, 'brand');
        }

        return $keys;
    }

    /**
     * @return Collection<int, object{
     *     id: int|string,
     *     key: string,
     *     label: string,
     *     type: string,
     *     field_type: string|null,
     *     sort_options: string|null
     * }>
     */
    public function filterDefinitionsForCategory(int $categoryId): Collection
    {
        $categoryIds = $this->categoryPaths->ancestorIdsIncludingSelf($categoryId);
        $filters = collect();

        if ($this->brandSettings->isEnabled()) {
            $filters->push((object) [
                'id' => 'brand',
                'key' => 'brand',
                'label' => 'Brand',
                'type' => 'brand',
                'field_type' => null,
                'sort_options' => 'alpha_asc',
            ]);
        }

        if ($categoryIds === []) {
            return $filters;
        }

        $fields = CustomField::query()
            ->where('status', true)
            ->where('is_product_filter', true)
            ->whereIn('field_type', ['single_select', 'multi_select'])
            ->whereNotNull('product_filter_key')
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
            ->orderBy('field_order')
            ->get();

        foreach ($fields as $field) {
            $filters->push((object) [
                'id' => $field->id,
                'key' => (string) $field->product_filter_key,
                'label' => (string) $field->label,
                'type' => 'custom_field',
                'field_type' => (string) $field->field_type,
                'sort_options' => $field->sort_options,
            ]);
        }

        return $filters;
    }

    public function resolveCustomFieldIdByKey(string $filterKey): ?int
    {
        if ($filterKey === 'brand') {
            return null;
        }

        return CustomField::query()
            ->where('product_filter_key', $filterKey)
            ->where('is_product_filter', true)
            ->value('id');
    }

    /**
     * @return list<int>
     */
    public function categoryScopeIds(?int $categoryId): array
    {
        if ($categoryId === null || $categoryId <= 0) {
            return [];
        }

        return $this->categoryPaths->descendantIdsIncludingSelf($categoryId);
    }
}
