<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminProductBulkService
{
    public function __construct(
        private readonly ProductModerationService $moderation,
    ) {}

    /**
     * @param  list<int>  $productIds
     */
    public function softDelete(array $productIds): int
    {
        $ids = $this->normalizeIds($productIds);

        return Product::query()
            ->whereIn('id', $ids)
            ->update([
                'is_deleted' => true,
                'is_active' => false,
                'status' => 'hidden',
                'visibility' => 'hidden',
            ]);
    }

    /**
     * @param  list<int>  $productIds
     */
    public function approve(array $productIds): int
    {
        $count = 0;

        foreach ($this->normalizeIds($productIds) as $id) {
            $product = Product::query()->find($id);
            if ($product === null) {
                continue;
            }

            $this->moderation->approve($product);
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<int>  $productIds
     */
    public function reject(array $productIds, string $reason): int
    {
        $count = 0;

        foreach ($this->normalizeIds($productIds) as $id) {
            $product = Product::query()->find($id);
            if ($product === null) {
                continue;
            }

            $this->moderation->reject($product, $reason);
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<int>  $productIds
     */
    public function restore(array $productIds): int
    {
        return Product::query()
            ->whereIn('id', $this->normalizeIds($productIds))
            ->where('is_deleted', true)
            ->update([
                'is_deleted' => false,
                'is_active' => true,
            ]);
    }

    /**
     * @param  list<int>  $productIds
     */
    public function deletePermanently(array $productIds): int
    {
        $ids = $this->normalizeIds($productIds);
        $count = 0;

        DB::transaction(function () use ($ids, &$count): void {
            $products = Product::query()->whereIn('id', $ids)->get();

            foreach ($products as $product) {
                $product->delete();
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  list<int>  $productIds
     * @return list<int>
     */
    private function normalizeIds(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => (int) $id,
            $productIds,
        ), static fn (int $id) => $id > 0)));

        if ($ids === []) {
            throw ValidationException::withMessages([
                'product_ids' => ['Select at least one product.'],
            ]);
        }

        return $ids;
    }
}
