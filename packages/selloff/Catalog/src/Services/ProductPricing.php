<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Models\ProductVariant;

final class ProductPricing
{
    public static function unitPrice(Product $product, ?ProductVariant $variant = null): string
    {
        if ($variant) {
            if ($variant->price_discounted !== null && (float) $variant->price_discounted > 0) {
                return number_format((float) $variant->price_discounted, 2, '.', '');
            }

            return number_format((float) $variant->price, 2, '.', '');
        }

        if ($product->price_discounted !== null && (float) $product->price_discounted > 0) {
            return number_format((float) $product->price_discounted, 2, '.', '');
        }

        return number_format((float) $product->price, 2, '.', '');
    }

    public static function isPurchasable(Product $product, int $quantity = 1, ?ProductVariant $variant = null): bool
    {
        if (! $product->is_active || $product->status !== 'published' || $product->visibility !== 'visible') {
            return false;
        }

        if ($product->is_sold && ! $product->multiple_sale) {
            return false;
        }

        $stock = $variant ? (int) $variant->stock : (int) $product->stock;

        return $stock >= $quantity;
    }
}
