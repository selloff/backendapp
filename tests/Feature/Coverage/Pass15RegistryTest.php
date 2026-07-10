<?php

test('parity matrix has no waived partial rows', function () {
    $contents = file_get_contents(monorepo_path('docs/spa-parity-matrix.csv')) ?: '';

    $this->assertStringNotContainsString('"waived-partial"', $contents);
});

test('waived matrix rows have registry notes', function () {
    $registry = loadRegistry_in_Pass15Registry();
    $missing = [];

    foreach (matrixRows_in_Pass15Registry() as $row) {
        if ($row['status'] !== 'waived') {
            continue;
        }

        if (! hasWaiveNote_in_Pass15Registry($row['legacy_path'], $registry)) {
            $missing[] = $row['legacy_path'];
        }
    }

    expect($missing)->toBe([]);
});

test('done rows never have ui parity none', function () {
    $violations = [];

    foreach (matrixRows_in_Pass15Registry() as $row) {
        if ($row['status'] === 'done' && $row['ui_parity'] === 'none') {
            $violations[] = $row['legacy_path'];
        }
    }

    expect($violations)->toBe([]);
});

test('waived rows have empty spa path', function () {
    $violations = [];

    foreach (matrixRows_in_Pass15Registry() as $row) {
        if ($row['status'] === 'waived' && $row['spa_path'] !== '') {
            $violations[] = $row['legacy_path'].' → '.$row['spa_path'];
        }
    }

    expect($violations)->toBe([]);
});

test('no legacy path in both done and waived registry maps', function () {
    $registry = loadRegistry_in_Pass15Registry();
    $done = $registry['done'] ?? [];
    $waived = $registry['waived'] ?? [];

    $conflicts = [];
    foreach (array_keys($done) as $legacyPath) {
        if (isset($waived[$legacyPath])) {
            $conflicts[] = $legacyPath;
        }
    }

    expect($conflicts)->toBe([]);
});

test('phase15 promoted paths are done in matrix', function () {
    $byPath = [];
    foreach (matrixRows_in_Pass15Registry() as $row) {
        $byPath[$row['legacy_path']] = $row;
    }

    foreach ([
        'admin/affiliate_program.php' => '/admin/affiliate',
        'admin/category/add_custom_field.php' => '/admin/custom-fields',
        'admin/category/edit_custom_field.php' => '/admin/custom-fields',
        'admin/category/buld_category_upload.php' => '/admin/categories/import',
        'admin/brand/edit.php' => '/admin/brands/:id/edit',
        'admin/product/products.php' => '/admin/products',
    ] as $legacyPath => $spaPath) {
        $row = $byPath[$legacyPath] ?? null;
        expect($row)->not->toBeNull($legacyPath);
        expect($row['status'])->toBe('done');
        expect($row['spa_path'])->toBe($spaPath);
        expect($row['ui_parity'])->toBe('full');
    }
});

test('auto waive remaining pending is disabled', function () {
    $registry = loadRegistry_in_Pass15Registry();

    expect($registry['auto_waive_remaining_pending'] ?? true)->toBeFalse();
});

/**
 * @return array<string, mixed>
 */
function loadRegistry_in_Pass15Registry(): array
{
    return json_decode(file_get_contents(monorepo_path('docs/parity-route-registry.json')) ?: '{}', true);
}

/**
 * @return list<array{legacy_path: string, spa_path: string, status: string, ui_parity: string}>
 */
function matrixRows_in_Pass15Registry(): array
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

/**
 * @param  array<string, mixed>  $registry
 */
function hasWaiveNote_in_Pass15Registry(string $legacyPath, array $registry): bool
{
    if (! empty($registry['waived'][$legacyPath])) {
        return true;
    }

    foreach ($registry['waive_prefixes'] ?? [] as $rule) {
        $prefix = $rule['prefix'] ?? '';
        if ($prefix !== '' && str_starts_with($legacyPath, $prefix) && ! empty($rule['note'])) {
            return true;
        }
    }

    return false;
}
