<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductListingDailyMetric;
use App\Modules\Selloff\Catalog\Models\VendorListingContactView;
use App\Modules\Selloff\Catalog\Models\VendorListingDailyMetric;
use App\Modules\Selloff\Catalog\Support\ListingMetricsSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VendorListingMetricsRecorder
{
    /**
     * @param  list<int>  $productIds
     * @return int Number of impressions recorded
     */
    public function recordImpressions(array $productIds, Request $request): int
    {
        if (! ListingMetricsSchema::hasImpressionsColumn()) {
            return 0;
        }

        $viewerId = $request->user()?->id;
        $visitorKey = $this->visitorKey($viewerId, $request);
        $date = now()->toDateString();
        $recorded = 0;

        $products = Product::query()
            ->whereIn('id', array_values(array_unique(array_map('intval', $productIds))))
            ->get(['id', 'vendor_id']);

        foreach ($products as $product) {
            $vendorId = (int) $product->vendor_id;
            if ($vendorId <= 0) {
                continue;
            }

            if ($viewerId !== null && (int) $viewerId === $vendorId) {
                continue;
            }

            $dedupeKey = sprintf('listing-impression:%d:%s:%s', $product->id, $visitorKey, $date);
            if (! Cache::add($dedupeKey, true, now()->addDay())) {
                continue;
            }

            DB::transaction(function () use ($product, $vendorId): void {
                $this->metricForToday($vendorId)->increment('impressions');
                $this->productMetricForToday($vendorId, (int) $product->id)->increment('impressions');
            });

            $recorded++;
        }

        return $recorded;
    }

    public function recordProductView(Product $product, Request $request): void
    {
        $vendorId = (int) $product->vendor_id;
        if ($vendorId <= 0) {
            return;
        }

        $viewerId = $request->user()?->id;
        if ($viewerId !== null && (int) $viewerId === $vendorId) {
            return;
        }

        $dedupeKey = sprintf('listing-view:%d:%s:%s', $product->id, $viewerId ?? 'guest', $request->ip() ?? 'unknown');
        if (! Cache::add($dedupeKey, true, now()->addDay())) {
            return;
        }

        DB::transaction(function () use ($product, $vendorId, $viewerId, $request): void {
            $product->increment('pageviews');
            $metric = $this->metricForToday($vendorId);
            $metric->increment('traffic');

            $productMetric = $this->productMetricForToday($vendorId, (int) $product->id);
            $productMetric->increment('traffic');

            $visitorKey = $this->visitorKey($viewerId, $request);

            $inserted = DB::table('vendor_listing_metric_visitors')->insertOrIgnore([
                'vendor_id' => $vendorId,
                'metric_date' => now()->toDateString(),
                'visitor_key' => $visitorKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 1) {
                $metric->increment('visitors');
            }

            $productVisitorInserted = DB::table('product_listing_metric_visitors')->insertOrIgnore([
                'product_id' => $product->id,
                'metric_date' => now()->toDateString(),
                'visitor_key' => $visitorKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($productVisitorInserted === 1) {
                $productMetric->increment('visitors');
            }
        });
    }

    public function recordContactView(Product $product, ?User $viewer): void
    {
        $vendorId = (int) $product->vendor_id;
        if ($vendorId <= 0) {
            return;
        }

        if ($viewer !== null && (int) $viewer->id === $vendorId) {
            return;
        }

        DB::transaction(function () use ($product, $vendorId, $viewer): void {
            VendorListingContactView::query()->create([
                'vendor_id' => $vendorId,
                'product_id' => $product->id,
                'viewer_id' => $viewer?->id,
            ]);

            $this->metricForToday($vendorId)->increment('contact_views');
            $this->productMetricForToday($vendorId, (int) $product->id)->increment('contact_views');
        });
    }

    private function productMetricForToday(int $vendorId, int $productId): ProductListingDailyMetric
    {
        return ProductListingDailyMetric::query()->firstOrCreate(
            [
                'vendor_id' => $vendorId,
                'product_id' => $productId,
                'metric_date' => now()->toDateString(),
            ],
            [
                'traffic' => 0,
                'visitors' => 0,
                'contact_views' => 0,
                'impressions' => 0,
                'chats' => 0,
            ],
        );
    }

    private function metricForToday(int $vendorId): VendorListingDailyMetric
    {
        return VendorListingDailyMetric::query()->firstOrCreate(
            [
                'vendor_id' => $vendorId,
                'metric_date' => now()->toDateString(),
            ],
            [
                'traffic' => 0,
                'visitors' => 0,
                'contact_views' => 0,
                'impressions' => 0,
                'chats' => 0,
                'promotion_spend' => 0,
            ],
        );
    }

    private function visitorKey(?int $viewerId, Request $request): string
    {
        return $viewerId !== null
            ? 'user:'.$viewerId
            : 'ip:'.sha1((string) $request->ip());
    }
}
