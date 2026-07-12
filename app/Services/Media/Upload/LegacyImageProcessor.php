<?php

namespace App\Services\Media\Upload;

use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

class LegacyImageProcessor
{
    public function __construct(
        private readonly WatermarkService $watermarks,
        private readonly MediaUploadPlatformSettings $platformSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $definition
     * @return array{relative: string, absolute: string, filename: string, width: int}
     */
    public function optimize(
        array $definition,
        string $sourcePath,
        string $absoluteDirectory,
        string $relativeDirectory,
        ?string $token = null,
        ?string $extension = null,
    ): array {
        $method = (string) ($definition['method'] ?? 'resize');
        $width = isset($definition['width']) ? (int) $definition['width'] : null;
        $height = isset($definition['height']) ? (int) $definition['height'] : null;
        $prefix = (string) ($definition['prefix'] ?? 'img_');
        $quality = (int) ($definition['quality'] ?? 85);

        if ($method === 'cover' && $width && $height) {
            $prefix .= "{$width}x{$height}_";
        } elseif ($width) {
            $prefix .= 'w'.$width.'_';
        } elseif ($height) {
            $prefix .= 'h'.$height.'_';
        }

        $extension ??= $this->targetExtension($sourcePath);
        $filename = $prefix.Str::lower($token ?? Str::random(24)).'.'.$extension;
        $absolutePath = rtrim($absoluteDirectory, '/').'/'.$filename;
        $relativePath = trim($relativeDirectory.'/'.$filename, '/');

        $image = Image::load($sourcePath)->orientation();

        if ($method === 'cover' && $width && $height) {
            $image->fit(Fit::Crop, $width, $height);
        } elseif ($width && $height) {
            $image->width($width)->height($height);
        } elseif ($width) {
            $image->width($width);
        } elseif ($height) {
            $image->height($height);
        }

        $this->saveEncoded($image, $absolutePath, $extension, $quality);

        $targetWidth = $width ?? 0;
        if ($this->watermarks->shouldApply($definition['watermark'] ?? null, $targetWidth)) {
            $this->watermarks->applyToFile($absolutePath, $targetWidth);
        }

        return [
            'relative' => $relativePath,
            'absolute' => $absolutePath,
            'filename' => $filename,
            'width' => $targetWidth,
        ];
    }

    /**
     * @return array<string, array{relative: string, absolute: string, filename: string, width: int}>
     */
    public function productVariants(string $sourcePath, string $absoluteDirectory, string $relativeDirectory, ?string $watermarkType = 'product'): array
    {
        $sizes = config('media_uploads.product_sizes', []);
        $definition = [
            'method' => 'resize',
            'prefix' => 'img_',
            'quality' => 85,
            'watermark' => $watermarkType,
        ];

        $token = Str::lower(Str::random(24));
        $extension = $this->targetExtension($sourcePath);

        $variants = [];
        foreach ($sizes as $label => $width) {
            $variants[$label] = $this->optimize(
                array_merge($definition, ['width' => (int) $width, 'height' => null]),
                $sourcePath,
                $absoluteDirectory,
                $relativeDirectory,
                $token,
                $extension,
            );
        }

        return $variants;
    }

    /**
     * @return array<string, array{relative: string, absolute: string, filename: string}>
     */
    public function pwaLogos(string $sourcePath, string $absoluteDirectory, string $relativeDirectory): array
    {
        $sizes = [
            'lg' => [512, 512],
            'md' => [192, 192],
            'sm' => [144, 144],
        ];

        $output = [];
        foreach ($sizes as $key => [$width, $height]) {
            $filename = "pwa_{$width}x{$height}.png";
            $absolutePath = rtrim($absoluteDirectory, '/').'/'.$filename;
            $relativePath = trim($relativeDirectory.'/'.$filename, '/');

            Image::load($sourcePath)
                ->orientation()
                ->fit(Fit::Crop, $width, $height)
                ->save($absolutePath);

            $output[$key] = [
                'relative' => $relativePath,
                'absolute' => $absolutePath,
                'filename' => $filename,
            ];
        }

        return $output;
    }

    private function targetExtension(string $sourcePath): string
    {
        $original = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) ?: 'jpg';
        $format = $this->platformSettings->imageFormat();

        return match ($format) {
            'JPG', 'JPEG' => 'jpg',
            'PNG' => 'png',
            'WEBP' => $original === 'gif' ? 'gif' : 'webp',
            'ORIGINAL', 'original' => $original,
            default => $original,
        };
    }

    private function saveEncoded(Image $image, string $absolutePath, string $extension, int $quality): void
    {
        match ($extension) {
            'png' => $image->save($absolutePath),
            'gif' => $image->save($absolutePath),
            'webp' => $image->quality($quality)->save($absolutePath),
            default => $image->quality($quality)->save($absolutePath),
        };
    }
}
