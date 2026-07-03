<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Services\Platform\PlatformSettingsService;

class ProductEditedModerationService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function applyAfterVendorEdit(Product $product, User $user): void
    {
        if ($user->can('admin_panel')) {
            return;
        }

        if (! $this->isEligiblePublishedProduct($product)) {
            return;
        }

        $approveAfterEditing = (int) ($this->platformSettings->all()['approve_after_editing'] ?? 0);
        if ($approveAfterEditing === 0) {
            return;
        }

        $updates = [
            'is_edited' => true,
        ];

        if ($approveAfterEditing === 2) {
            $updates['status'] = 'pending';
            $updates['is_active'] = false;
            $updates['is_verified'] = false;
        }

        $product->update($updates);
    }

    private function isEligiblePublishedProduct(Product $product): bool
    {
        if ($product->is_deleted || $product->is_draft || $product->status === 'draft') {
            return false;
        }

        return in_array((string) $product->status, ['published', '1'], true);
    }
}
