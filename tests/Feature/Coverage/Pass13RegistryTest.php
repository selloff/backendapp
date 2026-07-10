<?php

test('parity matrix has no waived partial rows', function () {
    $contents = file_get_contents(monorepo_path('docs/spa-parity-matrix.csv')) ?: '';

    $this->assertStringNotContainsString(
        '"waived-partial"',
        $contents,
        'Run node scripts/resolve-waived-partial.mjs after registry updates.',
    );
});

test('waived matrix rows have registry notes', function () {
    $matrixPath = monorepo_path('docs/spa-parity-matrix.csv');
    $registryPath = monorepo_path('docs/parity-route-registry.json');
    $registry = json_decode(file_get_contents($registryPath) ?: '{}', true);

    $missing = [];
    foreach (matrixRows_in_Pass13Registry($matrixPath) as $row) {
        if ($row['status'] !== 'waived') {
            continue;
        }

        if (! hasWaiveNote_in_Pass13Registry($row['legacy_path'], $registry)) {
            $missing[] = $row['legacy_path'];
        }
    }

    expect($missing)->toBe([]);
});

test('promoted admin language and currency routes are done', function () {
    $registry = json_decode(file_get_contents(monorepo_path('docs/parity-route-registry.json')) ?: '{}', true);
    $done = $registry['done'] ?? [];

    expect($done['admin/language/translations.php']['spa_path'] ?? null)->toBe('/admin/languages');
    expect($done['admin/language/edit_language.php']['spa_path'] ?? null)->toBe('/admin/languages');
    expect($done['admin/currency/currency_settings.php']['spa_path'] ?? null)->toBe('/admin/currencies');
});

test('font manager rows are permanently waived with notes', function () {
    $registry = json_decode(file_get_contents(monorepo_path('docs/parity-route-registry.json')) ?: '{}', true);
    $waived = $registry['waived'] ?? [];

    expect($waived['admin/font/fonts.php'] ?? null)->not->toBeEmpty();
    expect($waived['admin/font/edit.php'] ?? null)->not->toBeEmpty();
});

/**
 * @return list<array{legacy_path: string, status: string}>
 */
function matrixRows_in_Pass13Registry(string $path): array
{
    $rows = [];
    $lines = array_slice(explode("\n", trim(str_replace("\r", '', file_get_contents($path) ?: ''))), 1);

    foreach ($lines as $line) {
        if (! preg_match('/^"([^"]*)","([^"]*)","([^"]*)","([^"]*)"(?:,"([^"]*)")?$/', $line, $matches)) {
            continue;
        }

        $rows[] = [
            'legacy_path' => $matches[2],
            'status' => $matches[4],
            'ui_parity' => $matches[5] ?? '',
        ];
    }

    return $rows;
}

/**
 * @param  array<string, mixed>  $registry
 */
function hasWaiveNote_in_Pass13Registry(string $legacyPath, array $registry): bool
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
