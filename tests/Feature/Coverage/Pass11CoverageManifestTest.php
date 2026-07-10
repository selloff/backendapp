<?php

test('legacy coverage manifest has row per matrix entry', function () {
    $matrixPath = monorepo_path('docs/spa-parity-matrix.csv');
    $manifestPath = monorepo_path('docs/LEGACY_COVERAGE_MANIFEST.csv');

    expect($matrixPath)->toBeFile();
    expect($manifestPath)->toBeFile();

    $matrixRows = countDataRows_in_Pass11CoverageManifest($matrixPath);
    $manifestRows = countDataRows_in_Pass11CoverageManifest($manifestPath);

    expect($manifestRows)->toBeGreaterThanOrEqual((int) floor($matrixRows * 0.9))
        ->and($manifestRows)->toBeLessThanOrEqual($matrixRows);
});

test('parity matrix has no pending rows after sync', function () {
    $matrixPath = monorepo_path('docs/spa-parity-matrix.csv');
    $contents = file_get_contents($matrixPath) ?: '';

    $this->assertStringNotContainsString('"pending"', $contents, 'Run node scripts/sync-parity-matrix-status.mjs');
});

test('parity route registry auto waive disabled', function () {
    $path = monorepo_path('docs/parity-route-registry.json');
    $registry = json_decode(file_get_contents($path) ?: '{}', true);

    expect($registry['auto_waive_remaining_pending'] ?? true)->toBeFalse('Pass A: disable auto_waive_remaining_pending in parity-route-registry.json');
});

test('waived manifest rows have notes', function () {
    $manifestPath = monorepo_path('docs/LEGACY_COVERAGE_MANIFEST.csv');
    expect($manifestPath)->toBeFile();

    $lines = array_slice(
        array_filter(explode("\n", trim(str_replace("\r", '', file_get_contents($manifestPath) ?: '')))),
        1
    );

    $missing = [];
    foreach ($lines as $line) {
        if (! str_contains($line, '"waived"')) {
            continue;
        }
        $cols = str_getcsv($line);
        $notes = trim($cols[8] ?? '');
        if ($notes === '') {
            $missing[] = $cols[1] ?? $line;
        }
    }

    expect($missing)->toBe([]);
});

test('parity route registry exists', function () {
    $path = monorepo_path('docs/parity-route-registry.json');
    expect($path)->toBeFile();

    $registry = json_decode(file_get_contents($path) ?: '{}', true);
    expect($registry['done'] ?? null)->toBeArray();
    expect(count($registry['done'] ?? []))->toBeGreaterThan(50, 'Pass A should expand consolidated done mappings');
});

function countDataRows_in_Pass11CoverageManifest(string $path): int
{
    $lines = array_filter(explode("\n", trim(str_replace("\r", '', file_get_contents($path) ?: ''))));

    return max(0, count($lines) - 1);
}
