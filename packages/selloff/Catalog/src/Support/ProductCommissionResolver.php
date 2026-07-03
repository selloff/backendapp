<?php

namespace App\Modules\Selloff\Catalog\Support;

use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;

class ProductCommissionResolver
{
    public function resolveForProduct(?Product $product, ?int $categoryId): float
    {
        if ($product !== null && $product->is_commission_set && $product->commission_rate !== null) {
            return (float) $product->commission_rate;
        }

        $category = $product?->category;
        if ($category === null && $categoryId !== null) {
            $category = Category::query()->find($categoryId);
        }

        if ($category !== null && $category->is_commission_set) {
            return (float) $category->commission_rate;
        }

        $default = config('selloff.platform_defaults.commission_rate', 5);

        return (float) $default;
    }
}
