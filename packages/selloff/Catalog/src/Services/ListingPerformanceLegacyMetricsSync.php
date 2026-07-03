<?php

namespace App\Modules\Selloff\Catalog\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ListingPerformanceLegacyMetricsSync
{
    /** Legacy pageviews are spread across the most recent year of activity. */
    private const MAX_DISTRIBUTION_DAYS = 365;

    private const BATCH_SIZE = 500;

    private const DEADLOCK_MAX_ATTEMPTS = 5;

    /**
     * Rebuild listing daily metrics from migrated products.pageviews.
     *
     * @return array{products: int, views: int, metric_rows: int}
     */
    public function syncAllFromDatabase(bool $dryRun = false, ?int $vendorId = null): array
    {
        $stats = ['products' => 0, 'views' => 0, 'metric_rows' => 0];

        $query = DB::table('products')
            ->where('pageviews', '>', 0)
            ->whereNotNull('vendor_id')
            ->where('vendor_id', '>', 0)
            ->select(['id', 'vendor_id', 'pageviews', 'created_at']);

        if ($vendorId !== null) {
            $query->where('vendor_id', $vendorId);
        }

        $productMetricBatch = [];

        foreach ($query->orderBy('id')->cursor() as $product) {
            $stats['products']++;
            $stats['views'] += (int) $product->pageviews;

            $distribution = $this->distributionForProduct(
                (int) $product->pageviews,
                Carbon::parse($product->created_at ?? now()),
            );

            if ($distribution === []) {
                continue;
            }

            if ($dryRun) {
                $stats['metric_rows'] += count($distribution);

                continue;
            }

            $contactViewsByDate = DB::table('product_listing_daily_metrics')
                ->where('product_id', $product->id)
                ->pluck('contact_views', 'metric_date')
                ->all();

            $this->retryOnDeadlock(function () use ($product): void {
                DB::table('product_listing_daily_metrics')->where('product_id', $product->id)->delete();
            });

            $now = now();
            foreach ($distribution as $metricDate => $views) {
                $productMetricBatch[] = [
                    'vendor_id' => $product->vendor_id,
                    'product_id' => $product->id,
                    'metric_date' => $metricDate,
                    'traffic' => $views,
                    'visitors' => $views,
                    'contact_views' => (int) ($contactViewsByDate[$metricDate] ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $stats['metric_rows']++;

                if (count($productMetricBatch) >= self::BATCH_SIZE) {
                    $this->upsertProductMetrics($productMetricBatch);
                    $productMetricBatch = [];
                }
            }
        }

        if (! $dryRun && $productMetricBatch !== []) {
            $this->upsertProductMetrics($productMetricBatch);
        }

        if (! $dryRun) {
            $this->rebuildVendorDailyMetrics($vendorId);
        }

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function distributionForProduct(int $pageviews, Carbon $createdAt): array
    {
        return $this->distributeViews($pageviews, $createdAt->copy()->startOfDay(), now()->startOfDay());
    }

    /**
     * Spread legacy lifetime pageviews across the product's active date range.
     *
     * @return array<string, int>
     */
    public function distributeViews(int $pageviews, Carbon $start, Carbon $end): array
    {
        if ($pageviews <= 0) {
            return [];
        }

        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        $dayCount = max(1, $start->diffInDays($end) + 1);

        if ($dayCount > self::MAX_DISTRIBUTION_DAYS) {
            $start = $end->copy()->subDays(self::MAX_DISTRIBUTION_DAYS - 1);
            $dayCount = self::MAX_DISTRIBUTION_DAYS;
        }

        $base = intdiv($pageviews, $dayCount);
        $remainder = $pageviews % $dayCount;
        $distribution = [];

        for ($offset = 0; $offset < $dayCount; $offset++) {
            $date = $start->copy()->addDays($offset);
            $views = $base + ($offset >= ($dayCount - $remainder) ? 1 : 0);
            if ($views <= 0) {
                continue;
            }

            $distribution[$date->toDateString()] = ($distribution[$date->toDateString()] ?? 0) + $views;
        }

        return $distribution;
    }

    private function rebuildVendorDailyMetrics(?int $vendorId): void
    {
        $this->retryOnDeadlock(function () use ($vendorId): void {
            $deleteQuery = DB::table('vendor_listing_daily_metrics');
            if ($vendorId !== null) {
                $deleteQuery->where('vendor_id', $vendorId)->delete();
            } else {
                $deleteQuery->delete();
            }
        });

        $aggregateQuery = DB::table('product_listing_daily_metrics')
            ->selectRaw('vendor_id, metric_date, SUM(traffic) as traffic, SUM(visitors) as visitors, SUM(contact_views) as contact_views')
            ->groupBy('vendor_id', 'metric_date');

        if ($vendorId !== null) {
            $aggregateQuery->where('vendor_id', $vendorId);
        }

        $batch = [];
        $now = now();

        foreach ($aggregateQuery->cursor() as $row) {
            $batch[] = [
                'vendor_id' => $row->vendor_id,
                'metric_date' => $row->metric_date,
                'traffic' => (int) $row->traffic,
                'visitors' => (int) $row->visitors,
                'contact_views' => (int) $row->contact_views,
                'chats' => 0,
                'promotion_spend' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= self::BATCH_SIZE) {
                $this->upsertVendorMetrics($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->upsertVendorMetrics($batch);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsertProductMetrics(array $rows): void
    {
        $this->retryOnDeadlock(function () use ($rows): void {
            DB::table('product_listing_daily_metrics')->upsert(
                $rows,
                ['product_id', 'metric_date'],
                ['vendor_id', 'traffic', 'visitors', 'contact_views', 'updated_at'],
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsertVendorMetrics(array $rows): void
    {
        $this->retryOnDeadlock(function () use ($rows): void {
            DB::table('vendor_listing_daily_metrics')->upsert(
                $rows,
                ['vendor_id', 'metric_date'],
                ['traffic', 'visitors', 'contact_views', 'chats', 'promotion_spend', 'updated_at'],
            );
        });
    }

    /**
     * @param  callable(): void  $callback
     */
    private function retryOnDeadlock(callable $callback): void
    {
        $attempt = 0;

        while (true) {
            try {
                $callback();

                return;
            } catch (QueryException $exception) {
                $attempt++;

                if ($attempt >= self::DEADLOCK_MAX_ATTEMPTS || ! $this->isDeadlock($exception)) {
                    throw $exception;
                }

                usleep(100_000 * $attempt);
            }
        }
    }

    private function isDeadlock(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return $sqlState === '40P01' || str_contains(strtolower($exception->getMessage()), 'deadlock detected');
    }
}
