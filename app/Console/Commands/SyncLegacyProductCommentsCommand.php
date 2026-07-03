<?php

namespace App\Console\Commands;

use App\LegacyImport\Data\LegacyProductComments;
use App\LegacyImport\Sync\LegacyProductCommentsSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncLegacyProductCommentsCommand extends Command
{
    protected $signature = 'selloff:sync-legacy-product-comments';

    protected $description = 'Upsert production legacy product comments after products are imported';

    public function handle(LegacyProductCommentsSync $sync): int
    {
        $requiredColumns = ['name', 'email', 'ip_address'];
        $missing = array_values(array_filter(
            $requiredColumns,
            fn (string $column): bool => ! Schema::hasColumn('comments', $column),
        ));

        if ($missing !== []) {
            $this->error('The comments table is missing columns: '.implode(', ', $missing));
            $this->line('Run package migrations first:');
            $this->line('  php artisan selloff:migrate');
            $this->line('Or only Review module:');
            $this->line('  php artisan migrate --path=packages/selloff/Review/src/Database/Migrations');

            return self::FAILURE;
        }

        $total = count(LegacyProductComments::rows());
        $synced = $sync->sync();
        $skipped = $total - $synced;

        $this->info("Synced {$synced} of {$total} legacy product comments.");

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} comments because their legacy product is not in the database yet.");
            $this->line('Run Pass 11 product import (or ensure products have matching legacy_id/id), then re-run this command.');
        }

        return self::SUCCESS;
    }
}
