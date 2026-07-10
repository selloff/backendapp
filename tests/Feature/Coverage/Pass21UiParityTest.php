<?php

namespace Tests\Feature\Coverage;

const PHASE_21_P3_ADMIN_PATHS = [
    'admin/affiliate_program.php',
    'admin/homepage_manager/homepage_manager.php',
    'admin/homepage_manager/edit_banner.php',
    'admin/slider/slider.php',
    'admin/slider/edit_slider.php',
    'admin/newsletter/newsletter.php',
    'admin/newsletter/send_email.php',
    'admin/seo_tools.php',
];

const PHASE_21_P3_ACCOUNT_VENDOR_PATHS = [
    'settings/affiliate_links.php',
    'dashboard/affiliate_program.php',
];

test('phase 21 p3 paths are ui parity full in matrix', function () {
    $byPath = [];
    foreach (matrixRows_in_Pass21UiParity() as $row) {
        $byPath[$row['legacy_path']] = $row;
    }

    $missing = [];
    foreach (array_merge(PHASE_21_P3_ADMIN_PATHS, PHASE_21_P3_ACCOUNT_VENDOR_PATHS) as $legacyPath) {
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

test('registry has at least one hundred twenty full paths', function () {
    $registry = json_decode(file_get_contents(monorepo_path('docs/parity-route-registry.json')) ?: '{}', true);
    $fullPaths = $registry['ui_parity']['full_legacy_paths'] ?? [];

    expect(count($fullPaths))->toBeGreaterThanOrEqual(120);
});

test('admin partial count zero after phase 21', function () {
    $adminPartial = 0;
    foreach (matrixRows_in_Pass21UiParity() as $row) {
        if ($row['status'] !== 'done' || $row['ui_parity'] !== 'partial') {
            continue;
        }
        if (str_starts_with($row['legacy_path'], 'admin/')) {
            $adminPartial++;
        }
    }

    expect($adminPartial)->toBe(0);
});

test('registry waived notes for ops and ajax tooling', function () {
    $registry = json_decode(file_get_contents(monorepo_path('docs/parity-route-registry.json')) ?: '{}', true);
    $waived = $registry['waived'] ?? [];

    expect($waived)->toHaveKey('admin/cache_system.php');
    expect($waived)->toHaveKey('admin/database_backup_download');
    expect($registry['waive_prefixes'] ?? [])->not->toBeEmpty();
});

/**
 * @return list<array{legacy_path: string, spa_path: string, status: string, ui_parity: string}>
 */
function matrixRows_in_Pass21UiParity(): array
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
