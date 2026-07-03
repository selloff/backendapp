<?php

namespace App\Modules\Selloff\Catalog\Support;

/**
 * Maps legacy products.status / is_draft / is_rejected to Laravel moderation fields.
 *
 * Legacy admin approval uses products.status (0 = pending, 1 = published).
 * The legacy products.verified column is unrelated and is not imported into is_verified.
 */
final class LegacyProductModerationMapper
{
    /**
     * @return array{status: string, is_verified: bool}
     */
    public static function fromLegacyRow(array $row): array
    {
        $legacyStatus = (int) ($row['status'] ?? 0);
        $isDraft = (bool) ((int) ($row['is_draft'] ?? 0));
        $isRejected = (bool) ((int) ($row['is_rejected'] ?? 0));

        return self::resolve($legacyStatus, $isDraft, $isRejected);
    }

    /**
     * @return array{status: string, is_verified: bool}
     */
    public static function resolve(int $legacyStatus, bool $isDraft, bool $isRejected): array
    {
        if ($isRejected) {
            return [
                'status' => 'hidden',
                'is_verified' => false,
            ];
        }

        if ($isDraft) {
            return [
                'status' => 'draft',
                'is_verified' => false,
            ];
        }

        return match ($legacyStatus) {
            1 => [
                'status' => 'published',
                'is_verified' => true,
            ],
            default => [
                'status' => 'pending',
                'is_verified' => false,
            ],
        };
    }
}
