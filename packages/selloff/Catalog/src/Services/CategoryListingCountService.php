<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryListingCountService
{
    private const CACHE_KEY = 'selloff.category_listing_counts';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return array<int, int>
     */
    public function countsByCategoryId(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            if (DB::table('category_paths')->exists()) {
                return $this->countsFromCategoryPaths();
            }

            return $this->countsWithParentRollup();
        });
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, int>
     */
    private function countsFromCategoryPaths(): array
    {
        $listed = Product::query()->listed()->select('products.id', 'products.category_id');

        $rows = DB::query()
            ->fromSub($listed, 'listed_products')
            ->join('category_paths', 'category_paths.category_id', '=', 'listed_products.category_id')
            ->groupBy('category_paths.ancestor_id')
            ->selectRaw('category_paths.ancestor_id as category_id, COUNT(*) as ads_count')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->category_id] = (int) $row->ads_count;
        }

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    private function countsWithParentRollup(): array
    {
        $direct = Product::query()
            ->listed()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as ads_count')
            ->groupBy('category_id')
            ->pluck('ads_count', 'category_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        if ($direct === []) {
            return [];
        }

        $parentMap = DB::table('categories')->pluck('parent_id', 'id');
        $rollup = [];

        foreach ($direct as $categoryId => $count) {
            $currentId = (int) $categoryId;

            while ($currentId > 0) {
                $rollup[$currentId] = ($rollup[$currentId] ?? 0) + $count;
                $parentId = $parentMap[$currentId] ?? null;
                $currentId = $parentId !== null ? (int) $parentId : 0;
            }
        }

        return $rollup;
    }
}
