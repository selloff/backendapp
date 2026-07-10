#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Run Pest Drift conversion when `pest --drift` fails due to extra PHPUnit argv.
 *
 * Usage: php scripts/run-drift.php [directory]
 */

require __DIR__.'/../vendor/autoload.php';

use Pest\Drift\Converters\CodeConverterFactory;
use Pest\Drift\Converters\DirectoryConverter;
use Pest\Drift\Converters\FileConverter;
use Pest\Drift\Finder\Finder;

$directory = $argv[1] ?? 'tests';
$directory = rtrim($directory, '/');

$finder = new Finder($directory);
$codeConverterFactory = new CodeConverterFactory;
$directoryConverter = new DirectoryConverter(new FileConverter($codeConverterFactory->codeConverter(), $directory));

$changedTotal = $directoryConverter->convert($finder, static function (bool $changed): void {
    echo $changed ? '✔' : '.';
});

echo PHP_EOL."Converted {$changedTotal} files in [{$directory}]".PHP_EOL;
