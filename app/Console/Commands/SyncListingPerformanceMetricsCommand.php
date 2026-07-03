<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Catalog\Services\ListingPerformanceLegacyMetricsSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncListingPerformanceMetricsCommand extends Command
{
    protected $signature = 'selloff:sync-listing-performance-metrics
                            {--vendor-id= : Rebuild metrics for a single vendor}
                            {--dry-run : Report counts without writing}';

    protected $description = 'Rebuild listing performance daily metrics from products.pageviews (legacy migration source of truth)';

    public function handle(ListingPerformanceLegacyMetricsSync $sync): int
    {
        if (! Schema::hasTable('product_listing_daily_metrics') || ! Schema::hasColumn('products', 'pageviews')) {
            $this->error('Run selloff:migrate first (listing performance tables missing).');

            return self::FAILURE;
        }

        $vendorId = $this->option('vendor-id');
        $vendorId = $vendorId !== null && $vendorId !== '' ? (int) $vendorId : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no database writes.');
        }

        $stats = $sync->syncAllFromDatabase($dryRun, $vendorId);

        $verb = $dryRun ? 'Would rebuild metrics for' : 'Rebuilt metrics for';
        $this->info("{$verb} {$stats['products']} product(s) covering {$stats['views']} legacy pageview(s) across {$stats['metric_rows']} daily row(s).");

        if (! $dryRun && $stats['products'] > 0) {
            $pageviewSum = (int) DB::table('products')
                ->when($vendorId !== null, fn ($query) => $query->where('vendor_id', $vendorId))
                ->sum('pageviews');
            $metricSum = (int) DB::table('product_listing_daily_metrics')
                ->when($vendorId !== null, fn ($query) => $query->where('vendor_id', $vendorId))
                ->sum('traffic');

            if ($pageviewSum !== $metricSum) {
                $this->warn("Traffic total ({$metricSum}) does not match products.pageviews ({$pageviewSum}). Re-run after product import or pageviews backfill.");
            }
        }

        if ($stats['products'] === 0) {
            $this->line('No products with pageviews found. Import legacy products or run selloff:backfill-product-pageviews first.');
        }

        return self::SUCCESS;
    }
}
