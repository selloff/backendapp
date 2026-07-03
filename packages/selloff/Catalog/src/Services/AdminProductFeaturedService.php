<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Validation\ValidationException;

class AdminProductFeaturedService
{
    public function add(Product $product, int $days): Product
    {
        $this->assertEligible($product);

        if ($product->is_promoted) {
            throw ValidationException::withMessages([
                'product' => ['Product is already featured.'],
            ]);
        }

        $product->update([
            'is_promoted' => true,
            'promoted_at' => now(),
            'promoted_until' => now()->addDays($days),
            'promote_plan' => null,
        ]);

        return $product->fresh();
    }

    public function remove(Product $product): Product
    {
        if (! $product->is_promoted) {
            throw ValidationException::withMessages([
                'product' => ['Product is not featured.'],
            ]);
        }

        $product->update([
            'is_promoted' => false,
            'promoted_at' => null,
            'promoted_until' => null,
            'promote_plan' => null,
        ]);

        return $product->fresh();
    }

    private function assertEligible(Product $product): void
    {
        if ($product->is_deleted || $product->is_draft || $product->is_sold) {
            throw ValidationException::withMessages([
                'product' => ['Deleted, draft, and sold products cannot be featured.'],
            ]);
        }
    }
}
