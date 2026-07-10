<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Services\ProductModerationEmailService;
use Illuminate\Validation\ValidationException;

class ProductModerationService
{
    public function __construct(
        private readonly ProductModerationEmailService $emails,
    ) {}

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

        $fresh = $product->fresh()->load(['translations', 'vendor.vendorProfile', 'category.translations', 'brand', 'images']);
        $this->emails->queueApproved($fresh);

        return $fresh;
    }

    public function reject(Product $product, ?string $reason = null): Product
    {
        if ($reason === null || trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['A rejection reason is required.'],
            ]);
        }

        $trimmedReason = trim($reason);

        $product->update([
            'is_verified' => false,
            'status' => 'hidden',
            'visibility' => 'hidden',
            'is_active' => false,
            'reject_reason' => $trimmedReason,
        ]);

        $fresh = $product->fresh()->load(['translations', 'vendor.vendorProfile', 'category.translations', 'brand', 'images']);
        $this->emails->queueRejected($fresh, $trimmedReason);

        return $fresh;
    }
}
