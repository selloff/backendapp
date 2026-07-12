<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Services\ProductModerationEmailService;
use Illuminate\Validation\ValidationException;

class ProductModerationService
{
    public function __construct(
        private readonly ProductModerationEmailService $emails,
        private readonly ProductEditStagingService $staging,
    ) {}

    public function approve(Product $product): Product
    {
        if ($product->is_edited && is_array($product->pending_changes) && $product->pending_changes !== []) {
            $this->staging->applyPendingChanges($product->fresh(['translations']));
        } else {
            $this->staging->captureApprovedSnapshot($product->fresh(['translations']));
        }

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

        if ($product->is_edited) {
            return $this->rejectEditedChanges($product, $trimmedReason);
        }

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

    private function rejectEditedChanges(Product $product, string $reason): Product
    {
        $this->staging->discardPendingChanges($product->fresh(['translations']));

        $product->update([
            'is_verified' => true,
            'status' => 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'is_edited' => false,
            'reject_reason' => null,
            'last_edit_reject_reason' => $reason,
            'last_edit_rejected_at' => now(),
        ]);

        $fresh = $product->fresh()->load(['translations', 'vendor.vendorProfile', 'category.translations', 'brand', 'images']);
        $this->emails->queueEditRejected($fresh, $reason);

        return $fresh;
    }
}
