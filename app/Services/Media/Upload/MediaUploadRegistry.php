<?php

namespace App\Services\Media\Upload;

use InvalidArgumentException;

class MediaUploadRegistry
{
    /**
     * @return list<string>
     */
    public static function contexts(): array
    {
        return array_keys(config('media_uploads.contexts', []));
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(string $context, ?string $variant = null): array
    {
        $definitions = config('media_uploads.contexts', []);
        if (! isset($definitions[$context])) {
            throw new InvalidArgumentException("Unknown media upload context [{$context}].");
        }

        $definition = $definitions[$context];

        if ($variant !== null && isset($definition['variants'][$variant])) {
            $definition = array_merge($definition, $definition['variants'][$variant]);
            $definition['variant'] = $variant;
        } elseif ($variant !== null && ! isset($definition['variants'])) {
            throw new InvalidArgumentException("Context [{$context}] does not support variant [{$variant}].");
        } elseif ($variant === null && isset($definition['variants'])) {
            $defaultVariant = $definition['default_variant'] ?? array_key_first($definition['variants']);
            $definition = array_merge($definition, $definition['variants'][$defaultVariant]);
            $definition['variant'] = $defaultVariant;
        }

        $definition['context'] = $context;

        return $definition;
    }

    public static function maxUploadKilobytes(string $context): int
    {
        return (int) (self::definition($context)['max_kb'] ?? 10240);
    }

    /**
     * @return list<string>
     */
    public static function allowedExtensions(string $context, ?string $variant = null): array
    {
        return self::definition($context, $variant)['extensions'] ?? [];
    }
}
