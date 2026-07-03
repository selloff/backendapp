<?php

namespace App\Console\Commands;

use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportMemory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillProductImageVariantsCommand extends Command
{
    protected $signature = 'selloff:backfill-product-image-variants
                            {--source= : MySQL dump with legacy images table}';

    protected $description = 'Backfill product_images.variant_paths from legacy image_small/default/big columns';

    public function handle(): int
    {
        LegacyImportMemory::applyConfiguredLimit();

        $source = (string) $this->option('source');
        if ($source === '' || ! is_file($source)) {
            $this->error('Provide --source=path/to/production-mysql-dump.sql');

            return self::FAILURE;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('product_images', 'variant_paths')) {
            $this->error('Run selloff:migrate first (variant_paths column missing).');

            return self::FAILURE;
        }

        $raisedLimit = LegacyImportMemory::raiseForLargeDump($source);
        if ($raisedLimit !== null) {
            $this->warn("Large dump detected; raised PHP memory_limit to {$raisedLimit}.");
        }

        $reader = new MySqlDumpReader($source);
        if (! $reader->hasTable('images')) {
            $this->error('Dump does not contain images table.');

            return self::FAILURE;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($reader->rows('images') as $row) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $skipped++;

                continue;
            }

            $variantPaths = $this->legacyImageVariantPaths($row);
            if ($variantPaths === []) {
                $skipped++;

                continue;
            }

            $defaultPath = $variantPaths['default'] ?? $variantPaths['big'] ?? $variantPaths['small'] ?? null;
            if ($defaultPath === null) {
                $skipped++;

                continue;
            }

            $affected = DB::table('product_images')
                ->where('id', $legacyId)
                ->orWhere('legacy_id', $legacyId)
                ->update([
                    'path' => $defaultPath,
                    'variant_paths' => json_encode($variantPaths, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

            if ($affected > 0) {
                $updated += $affected;
            } else {
                $skipped++;
            }
        }

        $this->info("Backfilled variant_paths on {$updated} product_images row(s). Skipped {$skipped}.");

        return self::SUCCESS;
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
