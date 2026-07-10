#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Repair drift/script damage: PIN headers wrongly passed to assert* helpers
 * or injected into test closure signatures.
 */

$root = dirname(__DIR__).'/tests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

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

    $content = str_replace('function (, adminPinHeaders())', 'function ()', $content);
    $content = str_replace('function (, superAdminPinHeaders())', 'function ()', $content);

    $content = preg_replace(
        '/->assert(JsonPath|DatabaseMissing|DatabaseHas)\(([^)]+),\s*adminPinHeaders\(\)\)/',
        '->assert$1($2)',
        $content,
    ) ?? $content;

    $content = preg_replace(
        '/->assert(JsonPath|DatabaseMissing|DatabaseHas)\(([^)]+),\s*superAdminPinHeaders\(\)\)/',
        '->assert$1($2)',
        $content,
    ) ?? $content;

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Fixed misplaced PIN headers in {$path}\n";
    }
}
