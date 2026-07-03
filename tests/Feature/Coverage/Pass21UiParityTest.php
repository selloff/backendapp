<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

class Pass21UiParityTest extends TestCase
{
    /** @var list<string> */
    private const PHASE_21_P3_ADMIN_PATHS = [
        'admin/affiliate_program.php',
        'admin/homepage_manager/homepage_manager.php',
        'admin/homepage_manager/edit_banner.php',
        'admin/slider/slider.php',
        'admin/slider/edit_slider.php',
        'admin/newsletter/newsletter.php',
        'admin/newsletter/send_email.php',
        'admin/seo_tools.php',
    ];

    /** @var list<string> */
    private const PHASE_21_P3_ACCOUNT_VENDOR_PATHS = [
        'settings/affiliate_links.php',
        'dashboard/affiliate_program.php',
    ];

    public function test_phase_21_p3_paths_are_ui_parity_full_in_matrix(): void
    {
        $byPath = [];
        foreach ($this->matrixRows() as $row) {
            $byPath[$row['legacy_path']] = $row;
        }

        $missing = [];
        foreach (array_merge(self::PHASE_21_P3_ADMIN_PATHS, self::PHASE_21_P3_ACCOUNT_VENDOR_PATHS) as $legacyPath) {
            $row = $byPath[$legacyPath] ?? null;
            if ($row === null) {
                $missing[] = $legacyPath.' (not in matrix)';
                continue;
            }
            if ($row['ui_parity'] !== 'full') {
                $missing[] = $legacyPath.' ('.$row['ui_parity'].')';
            }
        }

        $this->assertSame([], $missing, 'Run node scripts/sync-ui-parity.mjs');
    }

    public function test_registry_has_at_least_one_hundred_twenty_full_paths(): void
    {
        $registry = json_decode(file_get_contents(base_path('../docs/parity-route-registry.json')) ?: '{}', true);
        $fullPaths = $registry['ui_parity']['full_legacy_paths'] ?? [];

        $this->assertGreaterThanOrEqual(120, count($fullPaths));
    }

    public function test_admin_partial_count_zero_after_phase_21(): void
    {
        $adminPartial = 0;
        foreach ($this->matrixRows() as $row) {
            if ($row['status'] !== 'done' || $row['ui_parity'] !== 'partial') {
                continue;
            }
            if (str_starts_with($row['legacy_path'], 'admin/')) {
                $adminPartial++;
            }
        }

        $this->assertSame(0, $adminPartial);
    }

    public function test_registry_waived_notes_for_ops_and_ajax_tooling(): void
    {
        $registry = json_decode(file_get_contents(base_path('../docs/parity-route-registry.json')) ?: '{}', true);
        $waived = $registry['waived'] ?? [];

        $this->assertArrayHasKey('admin/cache_system.php', $waived);
        $this->assertArrayHasKey('admin/database_backup_download', $waived);
        $this->assertNotEmpty($registry['waive_prefixes'] ?? []);
    }

    /**
     * @return list<array{legacy_path: string, spa_path: string, status: string, ui_parity: string}>
     */
    private function matrixRows(): array
    {
        $path = base_path('../docs/spa-parity-matrix.csv');
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
}
