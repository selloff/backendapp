<?php

namespace App\Services\Media\Upload;

class WatermarkService
{
    public function __construct(
        private readonly MediaUploadPlatformSettings $settings,
    ) {}

    public function shouldApply(?string $watermarkType, int $targetWidth): bool
    {
        if ($watermarkType === null || $watermarkType === '') {
            return false;
        }

        $config = $this->settings->watermark();
        $text = trim((string) ($config['text'] ?? ''));
        if ($text === '') {
            return false;
        }

        $enabled = match ($watermarkType) {
            'product' => (bool) ($config['product_enabled'] ?? false),
            'blog' => (bool) ($config['blog_enabled'] ?? false),
            default => false,
        };

        if (! $enabled) {
            return false;
        }

        $defaultWidth = (int) config('media_uploads.product_sizes.default', 960);
        $smallWidth = (int) config('media_uploads.product_sizes.small', 480);

        if ($targetWidth < $defaultWidth && ! ($config['thumbnail_enabled'] ?? false)) {
            return false;
        }

        if ($targetWidth > $defaultWidth) {
            return true;
        }

        return $targetWidth >= $smallWidth || ($config['thumbnail_enabled'] ?? false);
    }

    public function applyToFile(string $path, int $targetWidth): void
    {
        $config = $this->settings->watermark();
        $text = trim((string) ($config['text'] ?? ''));
        $fontPath = (string) ($config['font_path'] ?? '');

        if ($text === '' || ! is_file($path) || ! function_exists('imagecreatefromstring')) {
            return;
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return;
        }

        $fontSize = (float) ($config['font_size'] ?? 28);
        $defaultWidth = (int) config('media_uploads.product_sizes.default', 960);
        $smallWidth = (int) config('media_uploads.product_sizes.small', 480);

        if ($targetWidth > $defaultWidth) {
            $fontSize *= 1.8;
        } elseif ($targetWidth <= $smallWidth) {
            $fontSize *= 0.72;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $hAlign = (string) ($config['horizontal'] ?? 'right');
        $vAlign = (string) ($config['vertical'] ?? 'bottom');

        $x = match ($hAlign) {
            'left' => 15,
            'center' => (int) ($width / 2),
            default => $width - 15,
        };

        $y = match ($vAlign) {
            'top' => 15,
            'middle', 'center' => (int) ($height / 2),
            default => $height - 15,
        };

        $color = imagecolorallocatealpha($image, 255, 255, 255, 64);

        if (is_file($fontPath) && function_exists('imagettftext')) {
            imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
        } else {
            imagestring($image, 5, max(5, $x - 80), max(5, $y - 12), $text, $color);
        }

        $this->saveImage($image, $path);
        imagedestroy($image);
    }

    /**
     * @param  \GdImage  $image
     */
    private function saveImage($image, string $path): void
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        match ($extension) {
            'png' => imagepng($image, $path),
            'gif' => imagegif($image, $path),
            'webp' => function_exists('imagewebp') ? imagewebp($image, $path, 85) : imagejpeg($image, $path, 85),
            default => imagejpeg($image, $path, 85),
        };
    }
}
