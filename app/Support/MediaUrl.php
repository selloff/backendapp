<?php

namespace App\Support;

final class MediaUrl
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public static function storagePrefixFromSettings(array $settings): ?string
    {
        $storage = $settings['storage'] ?? null;

        if ($storage === 'aws_s3') {
            $bucket = trim((string) ($settings['aws_bucket'] ?? ''));
            $region = trim((string) ($settings['aws_region'] ?? ''));

            if ($bucket !== '' && $region !== '') {
                return "https://{$bucket}.s3.{$region}.amazonaws.com";
            }
        }

        if ($storage === 'cloudflare_r2') {
            $publicUrl = trim((string) ($settings['r2_public_url'] ?? ''));

            if ($publicUrl !== '') {
                return rtrim($publicUrl, '/');
            }
        }

        if ($storage === 'backblaze_b2') {
            $publicUrl = trim((string) ($settings['b2_public_url'] ?? ''));

            if ($publicUrl !== '') {
                return rtrim($publicUrl, '/');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function prefixForSettings(array $settings = []): string
    {
        $remotePrefix = self::storagePrefixFromSettings($settings);
        if ($remotePrefix !== null) {
            return $remotePrefix;
        }

        return self::prefix();
    }

    public static function resolve(?string $path, ?string $prefix = null): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:')) {
            return $path;
        }

        if (str_starts_with($path, '/') && ! str_starts_with($path, '/storage/')) {
            return self::absolute($path);
        }

        $prefix ??= rtrim((string) config('selloff.image_url_prefix', '/storage/'), '/');
        $url = rtrim($prefix, '/').'/'.ltrim($path, '/');

        return self::absolute($url);
    }

    public static function prefix(): string
    {
        return self::absolute(rtrim((string) config('selloff.image_url_prefix', '/storage/'), '/'));
    }

    public static function absolute(string $url): string
    {
        if (preg_match('#^https?://#', $url) === 1) {
            return $url;
        }

        if (! str_starts_with($url, '/')) {
            return $url;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        return $appUrl !== '' ? $appUrl.$url : $url;
    }
}
