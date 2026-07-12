<?php

namespace App\Modules\Selloff\Catalog\Console;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\ProductEditStagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillProductApprovedSnapshotsCommand extends Command
{
    protected $signature = 'selloff:backfill-product-approved-snapshots {--dry-run : Report counts without writing}';

    protected $description = 'Seed approved_snapshot for published verified products that do not yet have one';

    public function handle(ProductEditStagingService $staging): int
    {
        if (! Schema::hasColumn('products', 'approved_snapshot')) {
            $this->error('Missing column products.approved_snapshot. Run migrations first:');
            $this->line('  php artisan selloff:migrate');
            $this->line('  # or: php artisan migrate --path=packages/selloff/Catalog/src/Database/Migrations');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $updated = 0;
        $skipped = 0;

        Product::query()
            ->with('translations')
            ->where('is_verified', true)
            ->where('status', 'published')
            ->where('is_deleted', false)
            ->where('is_draft', false)
            ->whereNull('approved_snapshot')
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($staging, $dryRun, &$updated, &$skipped): void {
                foreach ($products as $product) {
                    if (is_array($product->approved_snapshot) && $product->approved_snapshot !== []) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $updated++;

                        continue;
                    }

                    $product->update([
                        'approved_snapshot' => $staging->buildSnapshotFromLive($product),
                    ]);
                    $updated++;
                }
            });

        $this->info(($dryRun ? 'Would update' : 'Updated')." {$updated} product snapshot(s). Skipped {$skipped}.");

        return self::SUCCESS;
    }
}
