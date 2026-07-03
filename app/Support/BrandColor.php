<?php

namespace App\Support;

final class BrandColor
{
    public const DEFAULT_PRIMARY = '#0075bb';

    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'default') {
            return self::DEFAULT_PRIMARY;
        }

        if (preg_match('/^#([0-9a-fA-F]{3,8})$/', $value, $matches) !== 1) {
            return self::DEFAULT_PRIMARY;
        }

        $hex = self::expandHex($matches[1]);
        if ($hex === null || ! self::isUsableMarketplacePrimary($hex)) {
            return self::DEFAULT_PRIMARY;
        }

        return '#'.$hex;
    }

    private static function expandHex(string $hex): ?string
    {
        $hex = strtolower($hex);

        if (strlen($hex) === 3) {
            return $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) === 6) {
            return $hex;
        }

        if (strlen($hex) === 8) {
            return substr($hex, 0, 6);
        }

        return null;
    }

    /**
     * Legacy admins can pick greys (#222222) for site_color; marketplace chrome expects a chromatic brand.
     */
    private static function isUsableMarketplacePrimary(string $hex): bool
    {
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        if ($delta === 0.0) {
            return false;
        }

        $lightness = ($max + $min) / 2;
        $saturation = $delta / (1 - abs(2 * $lightness - 1));

        return $saturation >= 0.15;
    }
}
