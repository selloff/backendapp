<?php

namespace App\Modules\Selloff\Shipping\Services;

use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Cart\Models\CartItem;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Collection;

class CartShippingMetrics
{
    public function sellerChargeableWeight(Cart $cart, int $sellerId): float
    {
        return $this->sellerItems($cart, $sellerId)
            ->reduce(function (float $total, CartItem $item): float {
                if ($item->product_type !== 'physical') {
                    return $total;
                }

                return $total + ($this->productChargeableWeight($item->product) * (int) $item->quantity);
            }, 0.0);
    }

    public function sellerItemCount(Cart $cart, int $sellerId): int
    {
        return (int) $this->sellerItems($cart, $sellerId)->sum('quantity');
    }

    public function sellerPhysicalSubtotal(Cart $cart, int $sellerId): float
    {
        return $this->sellerItems($cart, $sellerId)
            ->reduce(function (float $total, CartItem $item): float {
                if ($item->product_type !== 'physical') {
                    return $total;
                }

                return $total + (float) $item->total_price;
            }, 0.0);
    }

    /**
     * @return Collection<int, CartItem>
     */
    private function sellerItems(Cart $cart, int $sellerId): Collection
    {
        return $cart->items->where('seller_id', $sellerId)->values();
    }

    private function productChargeableWeight(?Product $product): float
    {
        if (! $product) {
            return 0.0;
        }

        $dimensions = $product->shipping_dimensions;
        if (is_array($dimensions) && isset($dimensions['weight']) && is_numeric($dimensions['weight'])) {
            return (float) $dimensions['weight'];
        }

        $chargeableWeight = $product->getAttribute('chargeable_weight');
        if ($chargeableWeight !== null && is_numeric($chargeableWeight)) {
            return (float) $chargeableWeight;
        }

        return 0.0;
    }
}
