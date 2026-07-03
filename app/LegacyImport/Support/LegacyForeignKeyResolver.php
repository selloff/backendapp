<?php

namespace App\LegacyImport\Support;

use App\LegacyImport\LegacyImportContext;
use Illuminate\Support\Facades\DB;

final class LegacyForeignKeyResolver
{
    public static function productId(LegacyImportContext $context, int $legacyProductId): ?int
    {
        if ($legacyProductId <= 0) {
            return null;
        }

        $mapped = $context->resolveId('products', $legacyProductId);
        if ($mapped !== null) {
            return $mapped;
        }

        $byLegacy = DB::table('products')->where('legacy_id', $legacyProductId)->value('id');

        if ($byLegacy !== null) {
            return (int) $byLegacy;
        }

        return DB::table('products')->where('id', $legacyProductId)->exists()
            ? $legacyProductId
            : null;
    }

    public static function userId(LegacyImportContext $context, int $legacyUserId): ?int
    {
        if ($legacyUserId <= 0) {
            return null;
        }

        $mapped = $context->resolveId('users', $legacyUserId);
        if ($mapped !== null) {
            return $mapped;
        }

        return DB::table('users')->where('id', $legacyUserId)->exists()
            ? $legacyUserId
            : null;
    }

    public static function commentId(LegacyImportContext $context, int $legacyCommentId): ?int
    {
        if ($legacyCommentId <= 0) {
            return null;
        }

        $mapped = $context->resolveId('comments', $legacyCommentId);
        if ($mapped !== null) {
            return $mapped;
        }

        $byLegacy = DB::table('comments')->where('legacy_id', $legacyCommentId)->value('id');

        if ($byLegacy !== null) {
            return (int) $byLegacy;
        }

        return DB::table('comments')->where('id', $legacyCommentId)->exists()
            ? $legacyCommentId
            : null;
    }

    public static function productIdWithoutContext(int $legacyProductId): ?int
    {
        if ($legacyProductId <= 0) {
            return null;
        }

        $byLegacy = DB::table('products')->where('legacy_id', $legacyProductId)->value('id');

        if ($byLegacy !== null) {
            return (int) $byLegacy;
        }

        return DB::table('products')->where('id', $legacyProductId)->exists()
            ? $legacyProductId
            : null;
    }

    public static function userIdWithoutContext(int $legacyUserId): ?int
    {
        if ($legacyUserId <= 0) {
            return null;
        }

        return DB::table('users')->where('id', $legacyUserId)->exists()
            ? $legacyUserId
            : null;
    }

    public static function blogPostIdWithoutContext(int $legacyPostId): ?int
    {
        if ($legacyPostId <= 0) {
            return null;
        }

        $byLegacy = DB::table('blog_posts')->where('legacy_id', $legacyPostId)->value('id');

        if ($byLegacy !== null) {
            return (int) $byLegacy;
        }

        return DB::table('blog_posts')->where('id', $legacyPostId)->exists()
            ? $legacyPostId
            : null;
    }

    public static function blogPostId(LegacyImportContext $context, int $legacyPostId): ?int
    {
        if ($legacyPostId <= 0) {
            return null;
        }

        $mapped = $context->resolveId('blog_posts', $legacyPostId);
        if ($mapped !== null) {
            return $mapped;
        }

        return self::blogPostIdWithoutContext($legacyPostId);
    }
}
