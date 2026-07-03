<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Validation\ValidationException;

class AdminProductSpecialOfferService
{
    public function add(Product $product): Product
    {
        $this->assertEligible($product);

        if ($product->is_special_offer) {
            throw ValidationException::withMessages([
                'product' => ['Product is already in special offers.'],
            ]);
        }

        $product->update([
            'is_special_offer' => true,
            'special_offer_at' => now(),
        ]);

        return $product->fresh();
    }

    public function remove(Product $product): Product
    {
        if (! $product->is_special_offer) {
            throw ValidationException::withMessages([
                'product' => ['Product is not in special offers.'],
            ]);
        }

        $product->update([
            'is_special_offer' => false,
            'special_offer_at' => null,
        ]);

        return $product->fresh();
    }

    private function assertEligible(Product $product): void
    {
        if ($product->is_deleted || $product->is_draft || $product->is_sold) {
            throw ValidationException::withMessages([
                'product' => ['Deleted, draft, and sold products cannot be added to special offers.'],
            ]);
        }
    }
}
