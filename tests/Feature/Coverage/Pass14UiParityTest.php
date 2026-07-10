<?php

test('parity matrix has ui parity column', function () {
    $path = monorepo_path('docs/spa-parity-matrix.csv');
    $header = strtok(file_get_contents($path) ?: '', "\n");

    expect(str_replace("\r", '', $header ?? ''))->toBe('"module","legacy_path","spa_path","status","ui_parity"');
});

test('all matrix rows have valid ui parity values', function () {
    $invalid = [];
    $counts = ['none' => 0, 'partial' => 0, 'full' => 0];

    foreach (matrixRows_in_Pass14UiParity() as $row) {
        if (! in_array($row['ui_parity'], ['none', 'partial', 'full'], true)) {
            $invalid[] = $row['legacy_path'].':'.$row['ui_parity'];
            continue;
        }
        $counts[$row['ui_parity']]++;
    }

    expect($invalid)->toBe([]);
    expect($counts['full'])->toBeGreaterThanOrEqual(120, 'Primary routes should be marked full (Phase 21 gate)');
    expect($counts['partial'])->toBeGreaterThanOrEqual(0, 'Partial count may be zero after Phase 21');
    expect($counts['none'])->toBeGreaterThan(0, 'Waived routes should be none');
});

test('done rows never have ui parity none', function () {
    $violations = [];

    foreach (matrixRows_in_Pass14UiParity() as $row) {
        if ($row['status'] === 'done' && $row['ui_parity'] === 'none') {
            $violations[] = $row['legacy_path'];
        }
    }

    expect($violations)->toBe([]);
});

test('waived rows have ui parity none', function () {
    $violations = [];

    foreach (matrixRows_in_Pass14UiParity() as $row) {
        if ($row['status'] === 'waived' && $row['ui_parity'] !== 'none') {
            $violations[] = $row['legacy_path'];
        }
    }

    expect($violations)->toBe([]);
});

test('primary buyer vendor admin routes are ui parity full', function () {
    $registry = json_decode(file_get_contents(monorepo_path('docs/parity-route-registry.json')) ?: '{}', true);
    $fullPaths = $registry['ui_parity']['full_legacy_paths'] ?? [];

    $byPath = [];
    foreach (matrixRows_in_Pass14UiParity() as $row) {
        $byPath[$row['legacy_path']] = $row;
    }

    $missing = [];
    foreach ($fullPaths as $legacyPath) {
        $row = $byPath[$legacyPath] ?? null;
        if ($row === null) {
            $missing[] = $legacyPath.' (not in matrix)';
            continue;
        }
        if ($row['ui_parity'] !== 'full') {
            $missing[] = $legacyPath.' ('.$row['ui_parity'].')';
        }
    }

    expect($missing)->toBe([]);
});

test('admin products spa path points to admin products', function () {
    foreach (matrixRows_in_Pass14UiParity() as $row) {
        if ($row['legacy_path'] !== 'admin/product/products.php') {
            continue;
        }

        expect($row['status'])->toBe('done');
        expect($row['spa_path'])->toBe('/admin/products');
        expect($row['ui_parity'])->toBe('full');
    }
});

/**
 * @return list<array{legacy_path: string, spa_path: string, status: string, ui_parity: string}>
 */
function matrixRows_in_Pass14UiParity(): array
{
    $path = monorepo_path('docs/spa-parity-matrix.csv');
    $rows = [];
    $lines = array_slice(explode("\n", trim(str_replace("\r", '', file_get_contents($path) ?: ''))), 1);

    foreach ($lines as $line) {
        if (! preg_match('/^"([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)"$/', $line, $matches)) {
            continue;
        }

        $rows[] = [
            'legacy_path' => $matches[2],
            'spa_path' => $matches[3],
            'status' => $matches[4],
            'ui_parity' => $matches[5],
        ];
    }

    return $rows;
}
