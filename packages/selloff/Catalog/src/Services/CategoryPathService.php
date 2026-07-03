<?php

namespace App\Modules\Selloff\Catalog\Services;

use Illuminate\Support\Facades\DB;

class CategoryPathService
{
    public function rebuildAll(): void
    {
        DB::table('category_paths')->delete();

        $categories = DB::table('categories')->select('id', 'parent_id')->get();
        if ($categories->isEmpty()) {
            return;
        }

        $parentMap = $categories->pluck('parent_id', 'id');
        $batch = [];

        foreach ($categories as $category) {
            $batch[] = [
                'category_id' => $category->id,
                'ancestor_id' => $category->id,
                'depth' => 0,
            ];

            $parentId = $category->parent_id;
            $depth = 1;

            while ($parentId !== null && $parentMap->has($parentId)) {
                $batch[] = [
                    'category_id' => $category->id,
                    'ancestor_id' => $parentId,
                    'depth' => $depth,
                ];
                $parentId = $parentMap[$parentId];
                $depth++;
            }
        }

        foreach (array_chunk($batch, 1000) as $chunk) {
            DB::table('category_paths')->insert($chunk);
        }
    }

    public function insertPaths(int $categoryId, ?int $parentId): void
    {
        $batch = [
            [
                'category_id' => $categoryId,
                'ancestor_id' => $categoryId,
                'depth' => 0,
            ],
        ];

        if ($parentId !== null) {
            $parentPaths = DB::table('category_paths')
                ->where('category_id', $parentId)
                ->get(['ancestor_id', 'depth']);

            foreach ($parentPaths as $path) {
                $batch[] = [
                    'category_id' => $categoryId,
                    'ancestor_id' => $path->ancestor_id,
                    'depth' => $path->depth + 1,
                ];
            }
        }

        DB::table('category_paths')->insert($batch);
    }

    public function moveSubtree(int $categoryId, ?int $newParentId): void
    {
        DB::statement(
            'DELETE FROM category_paths AS a
             USING category_paths AS d
             LEFT JOIN category_paths AS p
               ON a.ancestor_id = p.category_id AND p.ancestor_id = ?
             WHERE a.category_id = d.category_id
               AND d.ancestor_id = ?
               AND p.category_id IS NULL',
            [$categoryId, $categoryId],
        );

        if ($newParentId !== null) {
            DB::statement(
                'INSERT INTO category_paths (ancestor_id, category_id, depth)
                 SELECT supertree.ancestor_id, subtree.category_id, supertree.depth + subtree.depth + 1
                 FROM category_paths AS supertree
                 CROSS JOIN category_paths AS subtree
                 WHERE supertree.category_id = ? AND subtree.ancestor_id = ?',
                [$newParentId, $categoryId],
            );
        }
    }

    /**
     * @return list<int>
     */
    public function ancestorIdsIncludingSelf(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $fromPaths = DB::table('category_paths')
            ->where('category_id', $categoryId)
            ->pluck('ancestor_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($fromPaths !== []) {
            return $fromPaths;
        }

        $ids = [];
        $currentId = $categoryId;

        while ($currentId > 0) {
            $ids[] = $currentId;
            $parentId = DB::table('categories')->where('id', $currentId)->value('parent_id');
            $currentId = $parentId !== null ? (int) $parentId : 0;
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    public function descendantIdsIncludingSelf(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $fromPaths = DB::table('category_paths')
            ->where('ancestor_id', $categoryId)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($fromPaths !== []) {
            return $fromPaths;
        }

        return [$categoryId];
    }

    public function isDescendant(int $ancestorId, int $descendantId): bool
    {
        if ($ancestorId <= 0 || $descendantId <= 0) {
            return false;
        }

        return DB::table('category_paths')
            ->where('ancestor_id', $ancestorId)
            ->where('category_id', $descendantId)
            ->where('depth', '>', 0)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function parentChainIds(int $categoryId): array
    {
        $rows = DB::table('category_paths')
            ->where('category_id', $categoryId)
            ->where('depth', '>', 0)
            ->orderByDesc('depth')
            ->pluck('ancestor_id');

        return $rows->map(fn ($id) => (int) $id)->all();
    }

    public function rootCategoryId(int $categoryId): ?int
    {
        if ($categoryId <= 0) {
            return null;
        }

        $currentId = $categoryId;

        while ($currentId > 0) {
            $row = DB::table('categories')->where('id', $currentId)->first(['id', 'parent_id']);
            if ($row === null) {
                return null;
            }

            if ($row->parent_id === null) {
                return (int) $row->id;
            }

            $currentId = (int) $row->parent_id;
        }

        return null;
    }
}
