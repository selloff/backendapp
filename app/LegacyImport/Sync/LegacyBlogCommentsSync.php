<?php

namespace App\LegacyImport\Sync;

use App\LegacyImport\Data\LegacyBlogComments;
use App\LegacyImport\Support\LegacyForeignKeyResolver;
use Illuminate\Support\Facades\DB;

class LegacyBlogCommentsSync
{
    public function sync(): int
    {
        $rows = LegacyBlogComments::rows();
        $synced = 0;

        foreach ($rows as $row) {
            $blogPostId = LegacyForeignKeyResolver::blogPostIdWithoutContext((int) $row['post_id']);
            if ($blogPostId === null) {
                continue;
            }

            $legacyUserId = (int) ($row['user_id'] ?? 0);
            $userId = LegacyForeignKeyResolver::userIdWithoutContext($legacyUserId);

            $createdAt = $row['created_at'];
            $payload = [
                'blog_post_id' => $blogPostId,
                'user_id' => $userId,
                'name' => $row['name'] ?? null,
                'email' => $row['email'] ?? null,
                'comment' => $row['comment'] ?? '',
                'ip_address' => $row['ip_address'] ?? null,
                'status' => (int) ($row['status'] ?? 0) === 1 ? 'approved' : 'pending',
                'legacy_id' => (int) $row['id'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            $existing = DB::table('blog_comments')
                ->where('legacy_id', (int) $row['id'])
                ->orWhere('id', (int) $row['id'])
                ->first();

            if ($existing) {
                DB::table('blog_comments')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('blog_comments')->insert([
                    'id' => (int) $row['id'],
                    ...$payload,
                ]);
            }

            $synced++;
        }

        return $synced;
    }
}
