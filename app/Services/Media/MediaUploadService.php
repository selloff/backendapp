<?php

namespace App\Services\Media;

use App\Services\Media\Upload\LegacyImageProcessor;
use App\Services\Media\Upload\MediaUploadRegistry;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MediaUploadService
{
    private const CONTEXT_ALIASES = [
        'digital' => 'digital_file',
    ];

    public function __construct(
        private readonly LegacyImageProcessor $imageProcessor,
    ) {}

    /**
     * @return array{
     *     path: string,
     *     url: string,
     *     disk: string,
     *     filename: string,
     *     context: string,
     *     variants?: array<string, array{path: string, url: string, filename: string}>
     * }
     */
    public function upload(UploadedFile $file, string $context = 'temp', ?string $variant = null): array
    {
        $context = $this->normalizeContext($context);
        $definition = MediaUploadRegistry::definition($context, $variant);
        $this->assertAllowedExtension($file, $definition);

        $disk = (string) config('selloff.media_disk', 'public');
        $kind = (string) ($definition['kind'] ?? 'direct');

        $sourcePath = $this->storeIncomingFile($file);
        $directories = $this->directoriesFor($definition);
        $workDirectory = $directories['absolute'];

        try {
            return match ($kind) {
                'product_variants' => $this->uploadProductVariants($sourcePath, $directories, $disk, $context, $definition),
                'pwa_logos' => $this->uploadPwaLogos($sourcePath, $directories, $disk, $context),
                'optimized' => $this->uploadOptimized($sourcePath, $directories, $disk, $context, $definition),
                default => $this->uploadDirect($sourcePath, $file, $directories, $disk, $context, $definition),
            };
        } finally {
            @unlink($sourcePath);
            if (is_dir($workDirectory)) {
                File::deleteDirectory($workDirectory);
            }
        }
    }

    public function productVariantPath(string $path, string $variant = 'default'): string
    {
        $sizes = config('media_uploads.product_sizes', []);
        $targetWidth = $sizes[$variant] ?? null;

        if ($targetWidth === null || preg_match('/^https?:\/\//', $path) === 1) {
            return $path;
        }

        if (preg_match('/img_w\d+_/', $path) === 1) {
            return preg_replace('/img_w\d+_/', 'img_w'.$targetWidth.'_', $path, 1) ?? $path;
        }

        return $path;
    }

    public function urlForProductImage(string $path, ?string $disk = null, string $variant = 'default'): string
    {
        return $this->urlFor($this->productVariantPath($path, $variant), $disk);
    }

    /**
     * Resolve a product image URL using explicit legacy per-size paths when available.
     *
     * @param  array<string, string|null>|null  $variantPaths
     */
    public function urlForProductImageWithVariants(
        string $basePath,
        ?string $disk = null,
        string $variant = 'default',
        ?array $variantPaths = null,
    ): string {
        $explicitPath = $variantPaths[$variant] ?? null;
        if (is_string($explicitPath) && $explicitPath !== '') {
            return $this->urlFor($explicitPath, $disk);
        }

        return $this->urlForProductImage($basePath, $disk, $variant);
    }

    public function urlFor(string $path, ?string $disk = null): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return $path;
        }

        $disk ??= config('selloff.media_disk', 'public');

        if ($this->isRemoteObjectDisk($disk)) {
            $objectPath = $this->remoteObjectPath($path);
            $customUrl = config('filesystems.disks.s3.url');
            if (is_string($customUrl) && $customUrl !== '') {
                return rtrim($customUrl, '/').'/'.ltrim($objectPath, '/');
            }

            $bucket = config('filesystems.disks.s3.bucket');
            $region = config('filesystems.disks.s3.region', 'us-east-1');
            if (is_string($bucket) && $bucket !== '') {
                return "https://{$bucket}.s3.{$region}.amazonaws.com/{$objectPath}";
            }
        }

        $prefix = rtrim(config('selloff.image_url_prefix', '/storage/'), '/');

        return MediaUrl::absolute("{$prefix}/{$path}");
    }

    public function normalizeContext(string $context): string
    {
        return self::CONTEXT_ALIASES[$context] ?? $context;
    }

    /**
     * @return list<string>
     */
    public function allowedContexts(): array
    {
        $contexts = MediaUploadRegistry::contexts();

        return array_values(array_unique([...$contexts, ...array_keys(self::CONTEXT_ALIASES)]));
    }

    private function isRemoteObjectDisk(?string $disk): bool
    {
        return in_array($disk, ['s3', 'aws_s3', 'amazon_s3'], true);
    }

    /**
     * Legacy product image rows store `202604/img_….webp`; S3 keys are `uploads/images/202604/…`.
     */
    private function remoteObjectPath(string $path): string
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'uploads/')) {
            return $path;
        }

        $prefix = rtrim((string) config('selloff.legacy_product_image_prefix', 'uploads/images'), '/');

        return "{$prefix}/{$path}";
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{absolute: string, relative: string, storage: string, date_segment: string}
     */
    private function directoriesFor(array $definition): array
    {
        $folder = (string) ($definition['folder'] ?? 'temp');
        $dateSegment = ($definition['date_folder'] ?? false) ? now()->format('Ym') : '';
        $relative = 'uploads/'.$folder;
        if ($dateSegment !== '') {
            $relative .= '/'.$dateSegment;
        }

        $absolute = storage_path('app/media-work/'.Str::uuid());
        File::ensureDirectoryExists($absolute);

        return [
            'absolute' => $absolute,
            'relative' => $relative,
            'storage' => $relative,
            'date_segment' => $dateSegment,
        ];
    }

    private function storeIncomingFile(UploadedFile $file): string
    {
        $tempDir = storage_path('app/media-temp');
        File::ensureDirectoryExists($tempDir);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $tempPath = $tempDir.'/'.Str::uuid().'.'.$extension;
        $file->move(dirname($tempPath), basename($tempPath));

        return $tempPath;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertAllowedExtension(UploadedFile $file, array $definition): void
    {
        $allowed = $definition['extensions'] ?? [];
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

        if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
            throw new InvalidArgumentException("File type [{$extension}] is not allowed for this upload context.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{absolute: string, relative: string, storage: string, date_segment: string}  $directories
     * @return array{path: string, url: string, disk: string, filename: string, context: string}
     */
    private function uploadDirect(
        string $sourcePath,
        UploadedFile $file,
        array $directories,
        string $disk,
        string $context,
        array $definition,
    ): array {
        $prefix = (string) ($definition['prefix'] ?? '');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = $prefix.Str::lower(Str::random(24)).'.'.$extension;
        $storagePath = $directories['storage'].'/'.$filename;

        $this->putFile($disk, $storagePath, $sourcePath);

        return $this->buildResult($context, $storagePath, $filename, $disk, $definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{absolute: string, relative: string, storage: string, date_segment: string}  $directories
     * @return array{path: string, url: string, disk: string, filename: string, context: string}
     */
    private function uploadOptimized(
        string $sourcePath,
        array $directories,
        string $disk,
        string $context,
        array $definition,
    ): array {
        $processed = $this->imageProcessor->optimize(
            $this->optimizationDefinition($definition),
            $sourcePath,
            $directories['absolute'],
            $directories['relative'],
        );

        $this->putFile($disk, $processed['relative'], $processed['absolute']);

        return $this->buildResult($context, $processed['relative'], $processed['filename'], $disk, $definition);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array{absolute: string, relative: string, storage: string, date_segment: string}  $directories
     * @return array{
     *     path: string,
     *     url: string,
     *     disk: string,
     *     filename: string,
     *     context: string,
     *     variants: array<string, array{path: string, url: string, filename: string}>
     * }
     */
    private function uploadProductVariants(
        string $sourcePath,
        array $directories,
        string $disk,
        string $context,
        array $definition,
    ): array {
        $variants = $this->imageProcessor->productVariants(
            $sourcePath,
            $directories['absolute'],
            $directories['relative'],
            $definition['watermark'] ?? 'product',
        );

        $responseVariants = [];
        foreach ($variants as $label => $variant) {
            $this->putFile($disk, $variant['relative'], $variant['absolute']);
            $responseVariants[$label] = [
                'path' => $this->productDbPath($variant['relative']),
                'url' => $this->urlFor($this->productDbPath($variant['relative']), $disk),
                'filename' => $variant['filename'],
            ];
        }

        $default = $responseVariants['default'] ?? reset($responseVariants);

        return [
            'path' => $default['path'],
            'url' => $default['url'],
            'disk' => $disk,
            'filename' => $default['filename'],
            'context' => $context,
            'variants' => $responseVariants,
        ];
    }

    /**
     * @param  array{absolute: string, relative: string, storage: string, date_segment: string}  $directories
     * @return array{
     *     path: string,
     *     url: string,
     *     disk: string,
     *     filename: string,
     *     context: string,
     *     variants: array<string, array{path: string, url: string, filename: string}>
     * }
     */
    private function uploadPwaLogos(string $sourcePath, array $directories, string $disk, string $context): array
    {
        $logos = $this->imageProcessor->pwaLogos(
            $sourcePath,
            $directories['absolute'],
            $directories['relative'],
        );

        $responseVariants = [];
        foreach ($logos as $label => $logo) {
            $this->putFile($disk, $logo['relative'], $logo['absolute']);
            $responseVariants[$label] = [
                'path' => $logo['relative'],
                'url' => $this->urlFor($logo['relative'], $disk),
                'filename' => $logo['filename'],
            ];
        }

        $default = $responseVariants['lg'] ?? reset($responseVariants);

        return [
            'path' => $default['path'],
            'url' => $default['url'],
            'disk' => $disk,
            'filename' => $default['filename'],
            'context' => $context,
            'variants' => $responseVariants,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function optimizationDefinition(array $definition): array
    {
        return [
            'method' => $definition['method'] ?? 'resize',
            'prefix' => $definition['prefix'] ?? 'img_',
            'width' => $definition['width'] ?? null,
            'height' => $definition['height'] ?? null,
            'quality' => $definition['quality'] ?? 85,
            'watermark' => $definition['watermark'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{path: string, url: string, disk: string, filename: string, context: string}
     */
    private function buildResult(string $context, string $storagePath, string $filename, string $disk, array $definition): array
    {
        $apiPath = match ($context) {
            'product' => $this->productDbPath($storagePath),
            'attachment' => $this->stripUploadPrefix($storagePath, 'uploads/support'),
            default => $storagePath,
        };

        return [
            'path' => $apiPath,
            'url' => $this->urlFor($apiPath, $disk),
            'disk' => $disk,
            'filename' => $filename,
            'context' => $context,
        ];
    }

    private function productDbPath(string $storagePath): string
    {
        return $this->stripUploadPrefix($storagePath, 'uploads/images');
    }

    private function stripUploadPrefix(string $path, string $prefix): string
    {
        $prefix = rtrim($prefix, '/').'/';

        return str_starts_with($path, $prefix)
            ? substr($path, strlen($prefix))
            : $path;
    }

    private function putFile(string $disk, string $storagePath, string $localPath): void
    {
        $stream = fopen($localPath, 'r');
        if ($stream === false) {
            throw new InvalidArgumentException('Unable to read processed upload.');
        }

        Storage::disk($disk)->put($storagePath, $stream, ['visibility' => 'public']);
        fclose($stream);
    }
}
