<?php

namespace App\Modules\Selloff\Catalog\Support;

final class ProductDraftStatusSync
{
    /**
     * Keep status and is_draft aligned (legacy: is_draft=1 + draft, is_draft=0 + status=1).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function apply(array $attributes): array
    {
        if (! array_key_exists('status', $attributes)) {
            return $attributes;
        }

        return match ($attributes['status']) {
            'draft' => array_merge($attributes, [
                'is_draft' => true,
                'is_active' => false,
            ]),
            'published' => array_merge($attributes, [
                'is_draft' => false,
                'is_active' => $attributes['is_active'] ?? true,
            ]),
            'hidden' => array_merge($attributes, [
                'is_draft' => false,
                'visibility' => 'hidden',
                'is_active' => false,
            ]),
            default => $attributes,
        };
    }
}
