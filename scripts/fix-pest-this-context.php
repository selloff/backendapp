#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Replace $this-> with test()-> inside file-scoped Pest helper functions.
 */

$root = dirname(__DIR__).'/tests';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    if (str_contains($path, '/Helpers/') || str_contains($path, '/Concerns/') || str_ends_with($path, '/Pest.php')) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false || ! str_contains($content, '$this->')) {
        continue;
    }

    if (! preg_match_all('/^function (\w+)\s*\([^)]*\)(?:\s*:\s*[\w|\\\\?]+)?\s*\{/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    $changed = false;

    foreach ($matches[0] as $index => $match) {
        $functionName = $matches[1][$index][0];
        $start = $match[1];
        $bracePos = strpos($content, '{', $start);
        if ($bracePos === false) {
            continue;
        }

        $depth = 0;
        $end = $bracePos;
        $length = strlen($content);

        for ($i = $bracePos; $i < $length; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        $body = substr($content, $bracePos, $end - $bracePos + 1);
        if (! str_contains($body, '$this->')) {
            continue;
        }

        $fixedBody = str_replace('$this->', 'test()->', $body);
        $content = substr($content, 0, $bracePos).$fixedBody.substr($content, $end + 1);
        $changed = true;
    }

    if ($changed) {
        file_put_contents($path, $content);
        echo "Fixed \$this in helpers: {$path}\n";
    }
}
