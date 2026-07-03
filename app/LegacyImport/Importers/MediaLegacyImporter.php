<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class MediaLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'images';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable())) {
            return;
        }

        $this->importProductImages($context, $reader);
        $this->importMediaRefs($context, $reader);
    }

    private function importProductImages(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('images')) {
            return;
        }

        foreach ($reader->rows('images') as $row) {
            $context->notePlanned('images');

            $legacyId = (int) ($row['id'] ?? 0);
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($legacyId <= 0 || $productId === null) {
                $context->noteSkipped('images');

                continue;
            }

            $variantPaths = $this->legacyImageVariantPaths($row);
            $path = $variantPaths['default'] ?? $variantPaths['big'] ?? $variantPaths['small'] ?? null;
            if ($path === null || $path === '') {
                $context->noteSkipped('images');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'product_id' => $productId,
                'path' => (string) $path,
                'variant_paths' => $variantPaths === [] ? null : json_encode($variantPaths, JSON_THROW_ON_ERROR),
                'disk' => $row['storage'] ?? 'local',
                'sort_order' => (int) ($row['image_order'] ?? $row['sort_order'] ?? 0),
                'is_primary' => LegacyValueCoercer::bool($row['is_main'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('product_images')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'images', $legacyId, 'product_images', $legacyId);
            $context->noteImported('images');
        }
    }

    private function importMediaRefs(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('media') || ! ($context->shouldImportTable('media') || $context->shouldImportTable('images'))) {
            return;
        }

        foreach ($reader->rows('media') as $row) {
            $context->notePlanned('media');

            $legacyId = (int) ($row['id'] ?? 0);
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($legacyId <= 0 || $productId === null) {
                $context->noteSkipped('media');

                continue;
            }

            $path = $row['image_default'] ?? $row['file_path'] ?? $row['file_name'] ?? null;
            if ($path === null || $path === '') {
                $context->noteSkipped('media');

                continue;
            }

            $targetId = 1_000_000 + $legacyId;

            $payload = [
                'id' => $targetId,
                'product_id' => $productId,
                'path' => (string) $path,
                'disk' => $row['storage'] ?? 'local',
                'sort_order' => (int) ($row['image_order'] ?? 0),
                'is_primary' => false,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('product_images')->updateOrInsert(['legacy_id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'media', $legacyId, 'product_images', $targetId);
            $context->noteImported('media');
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{small?: string, default?: string, big?: string}
     */
    private function legacyImageVariantPaths(array $row): array
    {
        $paths = [];

        foreach ([
            'small' => 'image_small',
            'default' => 'image_default',
            'big' => 'image_big',
        ] as $variant => $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                $paths[$variant] = $value;
            }
        }

        return $paths;
    }
}
