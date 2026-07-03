<?php

namespace App\Console\Commands;

use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportMemory;
use App\Modules\Selloff\Catalog\Services\ListingPerformanceLegacyMetricsSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillProductPageviewsCommand extends Command
{
    protected $signature = 'selloff:backfill-product-pageviews
                            {--source= : MySQL dump with legacy products table}
                            {--dry-run : Report counts without writing}';

    protected $description = 'Backfill products.pageviews from legacy MySQL dump pageviews column';

    public function handle(ListingPerformanceLegacyMetricsSync $metricsSync): int
    {
        LegacyImportMemory::applyConfiguredLimit();

        $source = (string) $this->option('source');
        if ($source === '' || ! is_file($source)) {
            $this->error('Provide --source=path/to/production-mysql-dump.sql');

            return self::FAILURE;
        }

        if (! \Illuminate\Support\Facades\Schema::hasColumn('products', 'pageviews')) {
            $this->error('Run selloff:migrate first (products.pageviews column missing).');

            return self::FAILURE;
        }

        $raisedLimit = LegacyImportMemory::raiseForLargeDump($source);
        if ($raisedLimit !== null) {
            $this->warn("Large dump detected; raised PHP memory_limit to {$raisedLimit}.");
        }

        $reader = new MySqlDumpReader($source);
        if (! $reader->hasTable('products')) {
            $this->error('Dump does not contain products table.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($reader->rows('products') as $row) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $skipped++;

                continue;
            }

            $pageviews = max(0, (int) ($row['pageviews'] ?? 0));

            if (! DB::table('products')->where('id', $legacyId)->exists()) {
                $missing++;

                continue;
            }

            if ($dryRun) {
                $updated++;

                continue;
            }

            $affected = DB::table('products')
                ->where('id', $legacyId)
                ->update([
                    'pageviews' => $pageviews,
                    'updated_at' => now(),
                ]);

            if ($affected > 0) {
                $updated += $affected;
            } else {
                $skipped++;
            }
        }

        $verb = $dryRun ? 'Would backfill pageviews on' : 'Backfilled pageviews on';
        $this->info("{$verb} {$updated} product(s). Skipped {$skipped}. Missing in DB {$missing}.");

        if (! $dryRun && $updated > 0) {
            $metricStats = $metricsSync->syncAllFromDatabase();
            $this->info("Rebuilt listing performance metrics for {$metricStats['products']} product(s) ({$metricStats['views']} pageviews).");
        }

        return self::SUCCESS;
    }
}
