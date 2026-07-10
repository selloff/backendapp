#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Remove PHPUnit message arguments incorrectly kept by Pest Drift on expect().
 */

$root = dirname(__DIR__).'/tests';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

$patterns = [
    '/->toContain\(([^,]+),\s*"[^"]*"\)/' => '->toContain($1)',
    "/->toContain\\(([^,]+),\\s*'[^']*'\\)/" => '->toContain($1)',
    '/->toBe\(([^,]+),\s*\'[^\']*\'\)/' => '->toBe($1)',
    '/->toBe\(([^,]+),\s*"[^"]*"\)/' => '->toBe($1)',
];

foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    $original = $content;

    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content) ?? $content;
    }

    // PHPUnit assertArrayHasKey($array, $key, $message) drift artifact.
    $content = preg_replace(
        '/->toHaveKey\((\$[^,]+),\s*\1\)/',
        '->toHaveKey($1)',
        $content,
    ) ?? $content;

    // Drift kept message as second toBe arg with a variable (legacy path labels).
    $content = preg_replace(
        '/->toBe\((\'[^\']+\'|"[^"]+"),\s*\$legacyPath\)/',
        '->toBe($1)',
        $content,
    ) ?? $content;

    $content = preg_replace(
        '/->toBe\((\$spaPath),\s*\$legacyPath\)/',
        '->toBe($1)',
        $content,
    ) ?? $content;

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Fixed expect messages: {$path}\n";
    }
}
