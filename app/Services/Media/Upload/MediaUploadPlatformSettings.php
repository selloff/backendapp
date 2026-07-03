<?php

namespace App\Services\Media\Upload;

use App\Models\PlatformSetting;

class MediaUploadPlatformSettings
{

    public function imageFormat(): string
    {
        $value = $this->storedValue('image_file_format')
            ?? config('media_uploads.image_format', 'WEBP');

        $normalized = strtolower(trim((string) $value));

        return $normalized === 'original' ? 'original' : strtoupper((string) $value);
    }

    /**
     * @return array{
     *     product_enabled: bool,
     *     blog_enabled: bool,
     *     thumbnail_enabled: bool,
     *     text: string,
     *     font_path: string,
     *     font_size: float,
     *     horizontal: string,
     *     vertical: string
     * }
     */
    public function watermark(): array
    {
        $fallback = config('media_uploads.watermark', []);

        $vertical = (string) ($this->storedValue('watermark_vertical_align')
            ?? $fallback['vertical']
            ?? 'bottom');
        if ($vertical === 'center') {
            $vertical = 'middle';
        }

        return [
            'product_enabled' => $this->boolValue('watermark_product_enabled', $fallback['product_enabled'] ?? false),
            'blog_enabled' => $this->boolValue('watermark_blog_enabled', $fallback['blog_enabled'] ?? false),
            'thumbnail_enabled' => $this->boolValue('watermark_thumbnail_enabled', $fallback['thumbnail_enabled'] ?? false),
            'text' => (string) ($this->storedValue('watermark_text') ?? $fallback['text'] ?? ''),
            'font_path' => (string) ($fallback['font_path'] ?? ''),
            'font_size' => (float) ($this->storedValue('watermark_font_size') ?? $fallback['font_size'] ?? 28),
            'horizontal' => (string) ($this->storedValue('watermark_horizontal_align') ?? $fallback['horizontal'] ?? 'right'),
            'vertical' => $vertical,
        ];
    }

    private function storedValue(string $key): mixed
    {
        $setting = PlatformSetting::query()->find($key);

        return $setting?->value;
    }

    private function boolValue(string $key, mixed $fallback): bool
    {
        $value = $this->storedValue($key);
        if ($value === null) {
            return (bool) $fallback;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
