<?php

namespace App\LegacyImport\Sync;

use App\LegacyImport\Data\LegacyProductComments;
use App\LegacyImport\Support\LegacyForeignKeyResolver;
use Illuminate\Support\Facades\DB;

class LegacyProductCommentsSync
{
    public function sync(): int
    {
        $rows = LegacyProductComments::rows();
        usort($rows, fn (array $a, array $b): int => $a['parent_id'] <=> $b['parent_id']);

        $synced = 0;

        foreach ($rows as $row) {
            $productId = LegacyForeignKeyResolver::productIdWithoutContext((int) $row['product_id']);
            if ($productId === null) {
                continue;
            }

            $legacyParentId = (int) ($row['parent_id'] ?? 0);
            $parentId = $legacyParentId > 0
                ? DB::table('comments')->where('legacy_id', $legacyParentId)->orWhere('id', $legacyParentId)->value('id')
                : null;

            $legacyUserId = (int) ($row['user_id'] ?? 0);
            $userId = LegacyForeignKeyResolver::userIdWithoutContext($legacyUserId);

            $createdAt = $row['created_at'];
            $payload = [
                'product_id' => $productId,
                'user_id' => $userId,
                'parent_id' => $parentId,
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'comment' => $row['comment'] ?? null,
                'ip_address' => $row['ip_address'] ?? null,
                'is_approved' => (int) ($row['status'] ?? 0) === 1,
                'legacy_id' => (int) $row['id'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            $existing = DB::table('comments')
                ->where('legacy_id', (int) $row['id'])
                ->orWhere('id', (int) $row['id'])
                ->first();

            if ($existing) {
                DB::table('comments')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('comments')->insert([
                    'id' => (int) $row['id'],
                    ...$payload,
                ]);
            }

            $synced++;
        }

        return $synced;
    }
}
