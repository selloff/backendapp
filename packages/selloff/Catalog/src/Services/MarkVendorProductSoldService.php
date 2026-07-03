<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Http\Resources\Api\V1\ProductResource;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Validation\ValidationException;

class MarkVendorProductSoldService
{
    public function markSold(Product $product, User $vendor): ProductResource
    {
        abort_unless((int) $product->vendor_id === (int) $vendor->id, 403);

        $this->assertCanMarkSold($product);

        $updates = [
            'is_sold' => true,
            'visibility' => 'hidden',
            'is_active' => false,
        ];

        if ($product->type === 'physical') {
            $updates['stock'] = 0;
        }

        $product->update($updates);

        $product->load(['translations', 'category.translations', 'brand', 'images']);

        return new ProductResource($product->fresh());
    }

    private function assertCanMarkSold(Product $product): void
    {
        if ($product->is_deleted) {
            throw ValidationException::withMessages([
                'product' => ['This item has been deleted.'],
            ]);
        }

        if ($product->is_draft || $product->status === 'draft') {
            throw ValidationException::withMessages([
                'product' => ['Draft items cannot be marked as sold.'],
            ]);
        }

        if ($product->is_sold) {
            throw ValidationException::withMessages([
                'product' => ['This item is already marked as sold.'],
            ]);
        }

        if ($this->isPending($product)) {
            throw ValidationException::withMessages([
                'product' => ['Pending items cannot be marked as sold.'],
            ]);
        }
    }

    private function isPending(Product $product): bool
    {
        $status = (string) $product->status;

        return $status === 'pending' || $status === '0';
    }
}
