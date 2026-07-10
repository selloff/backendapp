#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Prefix file-scoped Pest helper functions to avoid redeclare fatals when
 * PHPUnit class-per-file isolation is gone.
 */

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS),
);

$skipFragments = [
    '/tests/Pest.php',
    '/tests/TestCase.php',
    '/tests/Helpers/',
    '/tests/Concerns/',
    '/Concerns/',
];

foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    foreach ($skipFragments as $fragment) {
        if (str_contains($path, $fragment)) {
            continue 2;
        }
    }

    $content = file_get_contents($path);
    if ($content === false || ! preg_match_all('/^function (\w+)\s*\(/m', $content, $matches)) {
        continue;
    }

    $funcNames = array_values(array_unique($matches[1]));
    if ($funcNames === []) {
        continue;
    }

    $stem = basename($path, '.php');
    $suffix = preg_replace('/Test$/', '', $stem) ?? $stem;

    foreach ($funcNames as $funcName) {
        if (str_ends_with($funcName, '_in_'.$suffix)) {
            continue;
        }

        $newName = "{$funcName}_in_{$suffix}";

        $content = preg_replace(
            '/(?<!function )(?<![\w$])'.preg_quote($funcName, '/').'\s*\(/',
            $newName.'(',
            $content,
        );

        $content = preg_replace(
            '/^function '.preg_quote($funcName, '/').'\s*\(/m',
            'function '.$newName.'(',
            $content,
        );
    }

    file_put_contents($path, $content);
    echo "Fixed helpers in {$path}\n";
}
