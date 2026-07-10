#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Add adminPinHeaders() to admin-scoped deleteJson calls missing PIN headers.
 */

$root = dirname(__DIR__).'/tests/Feature/Api/V1';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

foreach ($files as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    if (str_ends_with($path, 'AdminPinSecurityTest.php')) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false || ! str_contains($content, 'deleteJson(')) {
        continue;
    }

    $original = $content;

    $content = preg_replace_callback(
        '/\$this->deleteJson\(\s*([^,)]+)(?:,\s*(\[[^\]]*\]|[^,\)]+))?\s*\)/',
        static function (array $matches): string {
            $full = $matches[0];
            $uri = trim($matches[1]);
            $second = isset($matches[2]) ? trim($matches[2]) : null;

            if (str_contains($full, 'adminPinHeaders') || str_contains($full, 'HEADER_ADMIN_PIN')) {
                return $full;
            }

            if (! str_contains($uri, '/admin/') && ! str_contains($uri, '/api/v1/users/')) {
                return $full;
            }

            if (str_contains($uri, 'admin-pin')) {
                return $full;
            }

            if ($second === null) {
                return '$this->deleteJson('.$uri.', [], adminPinHeaders())';
            }

            if (str_starts_with($second, '[')) {
                return '$this->deleteJson('.$uri.', '.$second.', adminPinHeaders())';
            }

            return '$this->deleteJson('.$uri.', '.$second.', adminPinHeaders())';
        },
        $content,
    ) ?? $content;

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Patched deleteJson in {$path}\n";
    }
}
