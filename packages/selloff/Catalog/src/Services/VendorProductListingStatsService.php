<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\ProductListingDailyMetric;
use App\Modules\Selloff\Catalog\Support\ListingMetricsSchema;
use App\Modules\Selloff\Messaging\Models\Conversation;

class VendorProductListingStatsService
{
    /**
     * Lifetime listing stats for vendor product rows.
     *
     * @param  list<int>  $productIds
     * @param  array<int, int>  $lifetimePageviews
     * @return array<int, array{impressions: int, pageviews: int, phone_views: int, chats: int}>
     */
    public function lifetimeForProducts(array $productIds, array $lifetimePageviews = []): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return [];
        }

        $defaults = [];
        foreach ($productIds as $productId) {
            $defaults[$productId] = [
                'impressions' => 0,
                'pageviews' => max(0, (int) ($lifetimePageviews[$productId] ?? 0)),
                'phone_views' => 0,
                'chats' => 0,
            ];
        }

        if (! ListingMetricsSchema::hasProductDailyMetricsTable()) {
            return $this->attachChatCounts($defaults, $productIds);
        }

        $impressionsSelect = ListingMetricsSchema::impressionsSumSql();

        $metricRows = ProductListingDailyMetric::query()
            ->whereIn('product_id', $productIds)
            ->selectRaw("product_id, {$impressionsSelect}, SUM(contact_views) as phone_views")
            ->groupBy('product_id')
            ->get();

        foreach ($metricRows as $row) {
            $productId = (int) $row->product_id;
            if (! isset($defaults[$productId])) {
                continue;
            }

            $defaults[$productId]['impressions'] = (int) $row->impressions;
            $defaults[$productId]['phone_views'] = (int) $row->phone_views;
        }

        return $this->attachChatCounts($defaults, $productIds);
    }

    /**
     * @param  array<int, array{impressions: int, pageviews: int, phone_views: int, chats: int}>  $defaults
     * @param  list<int>  $productIds
     * @return array<int, array{impressions: int, pageviews: int, phone_views: int, chats: int}>
     */
    private function attachChatCounts(array $defaults, array $productIds): array
    {
        $chatRows = Conversation::query()
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, COUNT(*) as chats')
            ->groupBy('product_id')
            ->get();

        foreach ($chatRows as $row) {
            $productId = (int) $row->product_id;
            if (! isset($defaults[$productId])) {
                continue;
            }

            $defaults[$productId]['chats'] = (int) $row->chats;
        }

        return $defaults;
    }
}
