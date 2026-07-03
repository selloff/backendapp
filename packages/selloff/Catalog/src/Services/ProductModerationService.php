<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Validation\ValidationException;

class ProductModerationService
{
    public function approve(Product $product): Product
    {
        $product->update([
            'is_verified' => true,
            'status' => 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'is_draft' => false,
            'reject_reason' => null,
            'is_edited' => false,
        ]);

        return $product->fresh()->load(['translations', 'vendor.vendorProfile', 'category.translations', 'brand']);
    }

    public function reject(Product $product, ?string $reason = null): Product
    {
        if ($reason === null || trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['A rejection reason is required.'],
            ]);
        }

        $product->update([
            'is_verified' => false,
            'status' => 'hidden',
            'visibility' => 'hidden',
            'is_active' => false,
            'reject_reason' => trim($reason),
        ]);

        return $product->fresh()->load(['translations', 'vendor.vendorProfile', 'category.translations', 'brand']);
    }
}
