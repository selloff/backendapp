<?php

namespace App\Modules\Selloff\Content\Support;

class AdSpaceSlotRegistry
{
    /** @var array<string, string> */
    public const LEGACY_SLOTS = [
        'index_1' => 'Index Ad Space 1',
        'index_2' => 'Index Ad Space 2',
        'products_1' => 'Products Ad Space 1',
        'products_2' => 'Products Ad Space 2',
        'product_1' => 'Item Ad Space 1',
        'product_2' => 'Item Ad Space 2',
        'blog_1' => 'Blog Ad Space 1',
        'blog_2' => 'Blog Ad Space 2',
    ];

    public static function hasLegacySlot(string $key): bool
    {
        return array_key_exists($key, self::LEGACY_SLOTS);
    }

    /**
     * @return array<string, mixed>
     */
    public static function createDefaults(string $key): array
    {
        $dimensions = self::defaultDimensions($key);

        return [
            'ad_space_key' => $key,
            'title' => self::LEGACY_SLOTS[$key] ?? $key,
            'ad_code' => null,
            'ad_code_desktop' => null,
            'ad_code_mobile' => null,
            'url' => null,
            'is_active' => true,
            ...$dimensions,
        ];
    }

    /**
     * @return array{
     *     desktop_width: int,
     *     desktop_height: int,
     *     mobile_width: int,
     *     mobile_height: int
     * }
     */
    public static function defaultDimensions(string $key): array
    {
        $desktopWidth = in_array($key, ['sidebar_1', 'sidebar_2', 'products_sidebar', 'blog_post_details_sidebar'], true)
            ? 336
            : 728;
        $desktopHeight = in_array($key, ['sidebar_1', 'sidebar_2', 'products_sidebar', 'blog_post_details_sidebar'], true)
            ? 280
            : 90;

        return [
            'desktop_width' => $desktopWidth,
            'desktop_height' => $desktopHeight,
            'mobile_width' => 300,
            'mobile_height' => 250,
        ];
    }
}
