<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class BrandsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'brands';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('brands')) {
            return;
        }

        $names = $this->translationIndex($reader);

        foreach ($reader->rows('brands') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $name = $names[$legacyId] ?? $row['name'] ?? ('Brand '.$legacyId);

            $payload = [
                'id' => $legacyId,
                'name' => (string) $name,
                'image_path' => $row['image_path'] ?? null,
                'storage' => $row['storage'] ?? 'local',
                'show_on_slider' => LegacyValueCoercer::bool($row['show_on_slider'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('brands')->updateOrInsert(['id' => $legacyId], $payload);
                DB::table('brand_translations')->updateOrInsert(
                    ['brand_id' => $legacyId, 'locale' => 'en'],
                    ['name' => (string) $name, 'updated_at' => now(), 'created_at' => now()],
                );
            }

            $this->maps->remember($context, 'brands', $legacyId, 'brands', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }

    /**
     * @return array<int, string>
     */
    private function translationIndex(MySqlDumpReader $reader): array
    {
        if (! $reader->hasTable('brand_lang')) {
            return [];
        }

        $index = [];
        foreach ($reader->rows('brand_lang') as $row) {
            $brandId = (int) ($row['brand_id'] ?? 0);
            if ($brandId > 0 && ! empty($row['name'])) {
                $index[$brandId] = (string) $row['name'];
            }
        }

        return $index;
    }
}
