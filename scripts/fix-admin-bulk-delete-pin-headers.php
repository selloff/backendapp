#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Add adminPinHeaders() to admin bulk POST delete actions.
 */

$root = dirname(__DIR__).'/tests/Feature/Api/V1';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

foreach ($files as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $content = file_get_contents($path);
    if ($content === false || ! str_contains($content, 'admin/products/bulk') && ! str_contains($content, 'comments/bulk')) {
        continue;
    }

    $original = $content;

    // postJson(..., [ 'action' => 'delete...' ], ...) without adminPinHeaders on delete actions
    $content = preg_replace_callback(
        '/\$this->postJson\(\s*(\'[^\']*admin\/(?:products\/bulk|cms\/blog\/comments\/bulk|comments\/bulk)[^\']*\'|"[^"]*admin\/(?:products\/bulk|cms\/blog\/comments\/bulk|comments\/bulk)[^"]*")\s*,\s*(\[[\s\S]*?\])\s*\)/',
        static function (array $matches): string {
            $uri = $matches[1];
            $payload = $matches[2];

            if (str_contains($matches[0], 'adminPinHeaders') || str_contains($matches[0], 'HEADER_ADMIN_PIN')) {
                return $matches[0];
            }

            if (! preg_match("/'action'\s*=>\s*'(?:delete|delete_permanently)'/", $payload)) {
                return $matches[0];
            }

            return '$this->postJson('.$uri.', '.$payload.', adminPinHeaders())';
        },
        $content,
    ) ?? $content;

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Patched bulk delete postJson in {$path}\n";
    }
}
