<?php

namespace App\Modules\Selloff\Review\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\Wishlist;
use Illuminate\Support\Collection;

class WishlistService
{
    /**
     * @param  list<int>  $productIds
     * @return array{merged: int, skipped: int}
     */
    public function mergeGuestProducts(User $user, array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($productIds === []) {
            return ['merged' => 0, 'skipped' => 0];
        }

        $existing = Wishlist::query()
            ->where('user_id', $user->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();

        $validProductIds = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $merged = 0;
        $skipped = 0;

        foreach ($validProductIds as $productId) {
            if (in_array($productId, $existing, true)) {
                $skipped++;

                continue;
            }

            Wishlist::query()->create([
                'user_id' => $user->id,
                'product_id' => $productId,
            ]);
            $merged++;
        }

        return ['merged' => $merged, 'skipped' => $skipped];
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    public function guestPreviewProducts(array $productIds): Collection
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($productIds === []) {
            return collect();
        }

        return Product::query()
            ->with(['translations', 'images', 'vendor.vendorProfile', 'category'])
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $productIds, true))
            ->values();
    }
}
