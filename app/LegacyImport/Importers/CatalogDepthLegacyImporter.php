<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class CatalogDepthLegacyImporter extends MultiTableLegacyImporter
{
    /** @var array<string, int> */
    private array $normalizedTags = [];

    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return [
            'category_paths',
            'tags',
            'product_tags',
            'product_options',
            'product_option_values',
            'product_option_variants',
            'product_option_variant_values',
            'custom_fields',
            'custom_fields_category',
            'custom_fields_options',
            'custom_fields_product',
            'custom_field_lang',
            'custom_field_option_lang',
            'digital_files',
            'product_license_keys',
        ];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importCategoryPaths($context, $reader);
        $this->importTags($context, $reader);
        $this->importProductTags($context, $reader);
        $this->importProductOptions($context, $reader);
        $this->importProductOptionValues($context, $reader);
        $this->importProductVariants($context, $reader);
        $this->importProductVariantValues($context, $reader);
        $this->importCustomFields($context, $reader);
        $this->importCustomFieldCategories($context, $reader);
        $this->importCustomFieldOptions($context, $reader);
        $this->importCustomFieldProduct($context, $reader);
        $this->importDigitalFiles($context, $reader);
        $this->importLicenseKeys($context, $reader);
    }

    private function importCategoryPaths(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('category_paths') || ! $reader->hasTable('category_paths')) {
            return;
        }

        foreach ($reader->rows('category_paths') as $row) {
            $context->notePlanned('category_paths');

            $descendantId = (int) ($row['descendant_id'] ?? $row['category_id'] ?? 0);
            $ancestorId = (int) ($row['ancestor_id'] ?? 0);
            if ($descendantId <= 0 || $ancestorId <= 0) {
                $context->noteSkipped('category_paths');

                continue;
            }

            $categoryId = $context->resolveId('categories', $descendantId);
            $resolvedAncestorId = $context->resolveId('categories', $ancestorId);
            if ($categoryId === null || $resolvedAncestorId === null) {
                $context->noteSkipped('category_paths');

                continue;
            }

            if (! $context->dryRun) {
                DB::table('category_paths')->updateOrInsert(
                    ['category_id' => $categoryId, 'ancestor_id' => $resolvedAncestorId],
                    [
                        'category_id' => $categoryId,
                        'ancestor_id' => $resolvedAncestorId,
                        'depth' => (int) ($row['depth'] ?? 0),
                    ],
                );
            }

            $context->noteImported('category_paths');
        }
    }

    private function importTags(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('tags') || ! $reader->hasTable('tags')) {
            return;
        }

        foreach ($reader->rows('tags') as $row) {
            $context->notePlanned('tags');

            $legacyId = (int) ($row['id'] ?? 0);
            $rawTag = trim((string) ($row['tag'] ?? ''));
            if ($legacyId <= 0 || $rawTag === '') {
                $context->noteSkipped('tags');

                continue;
            }

            $tag = $this->normalizeUniqueTag($rawTag, $legacyId);

            if (! $context->dryRun) {
                DB::table('tags')->updateOrInsert(
                    ['id' => $legacyId],
                    [
                        'id' => $legacyId,
                        'tag' => $tag,
                        'lang_id' => (int) ($row['lang_id'] ?? 1) ?: 1,
                        'legacy_id' => $legacyId,
                        'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                        'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
                    ],
                );
            }

            $this->maps->remember($context, 'tags', $legacyId, 'tags', $legacyId);
            $this->normalizedTags[$tag] = $legacyId;
            $context->noteImported('tags');
        }
    }

    private function importProductTags(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('product_tags') || ! $reader->hasTable('product_tags')) {
            return;
        }

        foreach ($reader->rows('product_tags') as $row) {
            $context->notePlanned('product_tags');

            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($productId === null) {
                $context->noteSkipped('product_tags');

                continue;
            }

            $tagId = null;
            $tagLegacyId = (int) ($row['tag_id'] ?? 0);
            if ($tagLegacyId > 0) {
                $tagId = $context->resolveId('tags', $tagLegacyId);
            }

            $tagString = trim((string) ($row['tag'] ?? ''));
            if ($tagId === null && $tagString !== '') {
                $tagId = $this->resolveOrCreateTagId(
                    $context,
                    $tagString,
                    (int) ($row['lang_id'] ?? 1) ?: 1,
                );
            }

            if ($tagId === null) {
                $context->noteSkipped('product_tags');

                continue;
            }

            if (! $context->dryRun && $tagId !== null) {
                DB::table('product_tag')->updateOrInsert(
                    ['product_id' => $productId, 'tag_id' => $tagId],
                    ['product_id' => $productId, 'tag_id' => $tagId],
                );
            }

            $context->noteImported('product_tags');
        }
    }

    private function importProductOptions(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('product_options') || ! $reader->hasTable('product_options')) {
            return;
        }

        foreach ($reader->rows('product_options') as $row) {
            $context->notePlanned('product_options');

            $legacyId = (int) ($row['id'] ?? 0);
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($legacyId <= 0 || $productId === null) {
                $context->noteSkipped('product_options');

                continue;
            }

            $name = LegacyValueCoercer::localizedLabel(
                $row['option_name_translations'] ?? $row['option_name'] ?? $row['name'] ?? null,
                'Option '.$legacyId,
            );

            $payload = [
                'id' => $legacyId,
                'product_id' => $productId,
                'name' => $name,
                'sort_order' => (int) ($row['display_order'] ?? $row['sort_order'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('product_options')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'product_options', $legacyId, 'product_options', $legacyId);
            $context->noteImported('product_options');
        }
    }

    private function importProductOptionValues(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('product_option_values') || ! $reader->hasTable('product_option_values')) {
            return;
        }

        foreach ($reader->rows('product_option_values') as $row) {
            $context->notePlanned('product_option_values');

            $legacyId = (int) ($row['id'] ?? 0);
            $optionLegacyId = (int) ($row['option_id'] ?? $row['product_option_id'] ?? 0);
            $optionId = $context->resolveId('product_options', $optionLegacyId);
            if ($legacyId <= 0 || $optionId === null) {
                $context->noteSkipped('product_option_values');

                continue;
            }

            $value = LegacyValueCoercer::localizedLabel(
                $row['option_value_translations'] ?? $row['option_value'] ?? $row['value'] ?? null,
                'Value '.$legacyId,
            );

            $payload = [
                'id' => $legacyId,
                'product_option_id' => $optionId,
                'value' => $value,
                'sort_order' => (int) ($row['display_order'] ?? $row['sort_order'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('product_option_values')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'product_option_values', $legacyId, 'product_option_values', $legacyId);
            $context->noteImported('product_option_values');
        }
    }

    private function importProductVariants(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('product_option_variants') || ! $reader->hasTable('product_option_variants')) {
            return;
        }

        foreach ($reader->rows('product_option_variants') as $row) {
            $context->notePlanned('product_option_variants');

            $legacyId = (int) ($row['id'] ?? 0);
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($legacyId <= 0 || $productId === null) {
                $context->noteSkipped('product_option_variants');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'product_id' => $productId,
                'sku' => $row['sku'] ?? null,
                'variant_hash' => $row['variant_hash'] ?? null,
                'price' => LegacyValueCoercer::decimal($row['price'] ?? 0),
                'price_discounted' => isset($row['price_discounted']) && $row['price_discounted'] !== ''
                    ? LegacyValueCoercer::decimal($row['price_discounted'])
                    : null,
                'stock' => (int) ($row['quantity'] ?? $row['stock'] ?? 0),
                'is_default' => LegacyValueCoercer::bool($row['is_default'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('product_variants')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'product_option_variants', $legacyId, 'product_variants', $legacyId);
            $context->noteImported('product_option_variants');
        }
    }

    private function importProductVariantValues(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('product_option_variant_values') || ! $reader->hasTable('product_option_variant_values')) {
            return;
        }

        foreach ($reader->rows('product_option_variant_values') as $row) {
            $context->notePlanned('product_option_variant_values');

            $variantId = $context->resolveId('product_option_variants', (int) ($row['variant_id'] ?? 0));
            $valueId = $context->resolveId('product_option_values', (int) ($row['value_id'] ?? 0));
            if ($variantId === null || $valueId === null) {
                $context->noteSkipped('product_option_variant_values');

                continue;
            }

            if (! $context->dryRun) {
                DB::table('product_variant_option_values')->updateOrInsert(
                    ['product_variant_id' => $variantId, 'product_option_value_id' => $valueId],
                    ['product_variant_id' => $variantId, 'product_option_value_id' => $valueId],
                );
            }

            $context->noteImported('product_option_variant_values');
        }
    }

    private function importCustomFields(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('custom_fields') || ! $reader->hasTable('custom_fields')) {
            return;
        }

        $labels = $this->customFieldLabelIndex($reader);

        foreach ($reader->rows('custom_fields') as $row) {
            $context->notePlanned('custom_fields');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('custom_fields');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'field_type' => $row['field_type'] ?? null,
                'label' => LegacyValueCoercer::stringMax($labels[$legacyId] ?? ('Field '.$legacyId), 255, 'Field '.$legacyId),
                'is_required' => LegacyValueCoercer::bool($row['is_required'] ?? 0),
                'status' => LegacyValueCoercer::bool($row['status'] ?? 1),
                'field_order' => (int) ($row['field_order'] ?? 1),
                'is_product_filter' => LegacyValueCoercer::bool($row['is_product_filter'] ?? 0),
                'product_filter_key' => LegacyValueCoercer::stringMax($row['product_filter_key'] ?? null, 255),
                'sort_options' => $row['sort_options'] ?? 'alphabetically',
                'where_to_display' => (int) ($row['where_to_display'] ?? 2),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('custom_fields')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'custom_fields', $legacyId, 'custom_fields', $legacyId);
            $context->noteImported('custom_fields');
        }
    }

    /**
     * @return array<int, string>
     */
    private function customFieldLabelIndex(MySqlDumpReader $reader): array
    {
        if (! $reader->hasTable('custom_field_lang')) {
            return [];
        }

        $labels = [];
        foreach ($reader->rows('custom_field_lang') as $row) {
            $fieldId = (int) ($row['field_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($fieldId > 0 && $name !== '') {
                $labels[$fieldId] = LegacyValueCoercer::stringMax($name, 255, 'Field '.$fieldId);
            }
        }

        return $labels;
    }

    private function importCustomFieldCategories(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('custom_fields_category') || ! $reader->hasTable('custom_fields_category')) {
            return;
        }

        foreach ($reader->rows('custom_fields_category') as $row) {
            $context->notePlanned('custom_fields_category');

            $fieldId = $context->resolveId('custom_fields', (int) ($row['field_id'] ?? 0));
            $categoryId = $context->resolveId('categories', (int) ($row['category_id'] ?? 0));
            if ($fieldId === null || $categoryId === null) {
                $context->noteSkipped('custom_fields_category');

                continue;
            }

            if (! $context->dryRun) {
                DB::table('custom_field_category')->updateOrInsert(
                    ['custom_field_id' => $fieldId, 'category_id' => $categoryId],
                    ['custom_field_id' => $fieldId, 'category_id' => $categoryId],
                );
            }

            $context->noteImported('custom_fields_category');
        }
    }

    private function importCustomFieldOptions(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('custom_fields_options')) {
            return;
        }

        $labels = $this->customFieldOptionLabelIndex($reader);

        foreach ($reader->rows('custom_fields_options') as $row) {
            $context->notePlanned('custom_fields_options');

            $legacyId = (int) ($row['id'] ?? 0);
            $fieldId = $context->resolveId('custom_fields', (int) ($row['field_id'] ?? 0));
            if ($legacyId <= 0 || $fieldId === null) {
                $context->noteSkipped('custom_fields_options');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'custom_field_id' => $fieldId,
                'option_key' => (string) ($row['option_key'] ?? 'opt-'.$legacyId),
                'label' => LegacyValueCoercer::stringMax($labels[$legacyId] ?? ($row['option_key'] ?? 'Option '.$legacyId), 255, 'Option '.$legacyId),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('custom_field_options')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'custom_fields_options', $legacyId, 'custom_field_options', $legacyId);
            $context->noteImported('custom_fields_options');
        }
    }

    /**
     * @return array<int, string>
     */
    private function customFieldOptionLabelIndex(MySqlDumpReader $reader): array
    {
        if (! $reader->hasTable('custom_field_option_lang')) {
            return [];
        }

        $labels = [];
        foreach ($reader->rows('custom_field_option_lang') as $row) {
            $optionId = (int) ($row['option_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($optionId > 0 && $name !== '') {
                $labels[$optionId] = LegacyValueCoercer::stringMax($name, 255, 'Option '.$optionId);
            }
        }

        return $labels;
    }

    private function importCustomFieldProduct(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('custom_fields_product') || ! $reader->hasTable('custom_fields_product')) {
            return;
        }

        foreach ($reader->rows('custom_fields_product') as $row) {
            $context->notePlanned('custom_fields_product');

            $fieldId = $context->resolveId('custom_fields', (int) ($row['field_id'] ?? 0));
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($fieldId === null || $productId === null) {
                $context->noteSkipped('custom_fields_product');

                continue;
            }

            $optionLegacyId = (int) ($row['selected_option_id'] ?? 0);
            $optionId = $optionLegacyId > 0
                ? $context->resolveId('custom_fields_options', $optionLegacyId)
                : null;

            $payload = [
                'custom_field_id' => $fieldId,
                'product_id' => $productId,
                'product_filter_key' => $row['product_filter_key'] ?? null,
                'field_value' => $row['field_value'] ?? null,
                'custom_field_option_id' => $optionId,
            ];

            if (! $context->dryRun) {
                DB::table('custom_field_product')->updateOrInsert(
                    ['custom_field_id' => $fieldId, 'product_id' => $productId],
                    $payload,
                );
            }

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId > 0) {
                $newId = $context->dryRun
                    ? $legacyId
                    : (int) DB::table('custom_field_product')
                        ->where('custom_field_id', $fieldId)
                        ->where('product_id', $productId)
                        ->value('id');
                $this->maps->remember($context, 'custom_fields_product', $legacyId, 'custom_field_product', $newId);
            }

            $context->noteImported('custom_fields_product');
        }
    }

    private function importDigitalFiles(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('digital_files') || ! $reader->hasTable('digital_files')) {
            return;
        }

        foreach ($reader->rows('digital_files') as $row) {
            $context->notePlanned('digital_files');

            $legacyId = (int) ($row['id'] ?? 0);
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($legacyId <= 0 || $productId === null) {
                $context->noteSkipped('digital_files');

                continue;
            }

            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));

            $payload = [
                'id' => $legacyId,
                'product_id' => $productId,
                'user_id' => $userId,
                'file_name' => (string) ($row['file_name'] ?? 'file-'.$legacyId),
                'storage' => $row['storage'] ?? 'local',
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('digital_files')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'digital_files', $legacyId, 'digital_files', $legacyId);
            $context->noteImported('digital_files');
        }
    }

    private function importLicenseKeys(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('product_license_keys') || ! $reader->hasTable('product_license_keys')) {
            return;
        }

        foreach ($reader->rows('product_license_keys') as $row) {
            $context->notePlanned('product_license_keys');

            $legacyId = (int) ($row['id'] ?? 0);
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($legacyId <= 0 || $productId === null) {
                $context->noteSkipped('product_license_keys');

                continue;
            }

            $orderLegacyId = (int) ($row['order_id'] ?? 0);
            $orderId = $orderLegacyId > 0 ? $context->resolveId('orders', $orderLegacyId) : null;

            $payload = [
                'id' => $legacyId,
                'product_id' => $productId,
                'license_key' => (string) ($row['license_key'] ?? 'KEY-'.$legacyId),
                'is_used' => LegacyValueCoercer::bool($row['is_used'] ?? $row['status'] ?? 0),
                'order_id' => $orderId,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('product_license_keys')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'product_license_keys', $legacyId, 'product_license_keys', $legacyId);
            $context->noteImported('product_license_keys');
        }
    }

    private function resolveOrCreateTagId(LegacyImportContext $context, string $tagString, int $langId = 1): ?int
    {
        $normalizedTag = LegacyValueCoercer::stringMax($tagString, 255);
        if ($normalizedTag === null || $normalizedTag === '') {
            return null;
        }

        if (isset($this->normalizedTags[$normalizedTag])) {
            return $this->normalizedTags[$normalizedTag];
        }

        if (! $context->dryRun) {
            $existing = DB::table('tags')->where('tag', $normalizedTag)->value('id');
            if ($existing !== null) {
                $this->normalizedTags[$normalizedTag] = (int) $existing;

                return (int) $existing;
            }

            $newId = (int) DB::table('tags')->insertGetId([
                'tag' => $normalizedTag,
                'lang_id' => $langId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->normalizedTags[$normalizedTag] = $newId;

            return $newId;
        }

        $syntheticId = 900_000_000 + (int) sprintf('%u', crc32($normalizedTag)) % 99_999_999;
        $this->normalizedTags[$normalizedTag] = $syntheticId;

        return $syntheticId;
    }

    private function normalizeUniqueTag(string $rawTag, int $legacyId): string
    {
        $candidate = LegacyValueCoercer::stringMax($rawTag, 255, 'tag-'.$legacyId);
        if ($candidate === null || $candidate === '') {
            $candidate = 'tag-'.$legacyId;
        }

        if (! $this->isNormalizedTagTaken($candidate, $legacyId)) {
            $this->normalizedTags[$candidate] = $legacyId;

            return $candidate;
        }

        $suffixed = LegacyValueCoercer::stringMax($candidate.'-'.$legacyId, 255, 'tag-'.$legacyId);
        $this->normalizedTags[$suffixed] = $legacyId;

        return $suffixed;
    }

    private function isNormalizedTagTaken(string $tag, int $legacyId): bool
    {
        if (isset($this->normalizedTags[$tag]) && $this->normalizedTags[$tag] !== $legacyId) {
            return true;
        }

        return DB::table('tags')->where('tag', $tag)->where('id', '!=', $legacyId)->exists();
    }
}
