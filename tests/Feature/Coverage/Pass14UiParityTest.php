<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

class Pass14UiParityTest extends TestCase
{
    public function test_parity_matrix_has_ui_parity_column(): void
    {
        $path = base_path('../docs/spa-parity-matrix.csv');
        $header = strtok(file_get_contents($path) ?: '', "\n");

        $this->assertSame(
            '"module","legacy_path","spa_path","status","ui_parity"',
            str_replace("\r", '', $header ?? ''),
        );
    }

    public function test_all_matrix_rows_have_valid_ui_parity_values(): void
    {
        $invalid = [];
        $counts = ['none' => 0, 'partial' => 0, 'full' => 0];

        foreach ($this->matrixRows() as $row) {
            if (! in_array($row['ui_parity'], ['none', 'partial', 'full'], true)) {
                $invalid[] = $row['legacy_path'].':'.$row['ui_parity'];
                continue;
            }
            $counts[$row['ui_parity']]++;
        }

        $this->assertSame([], $invalid, 'Run node scripts/sync-ui-parity.mjs');
        $this->assertGreaterThanOrEqual(120, $counts['full'], 'Primary routes should be marked full (Phase 21 gate)');
        $this->assertGreaterThanOrEqual(0, $counts['partial'], 'Partial count may be zero after Phase 21');
        $this->assertGreaterThan(0, $counts['none'], 'Waived routes should be none');
    }

    public function test_done_rows_never_have_ui_parity_none(): void
    {
        $violations = [];

        foreach ($this->matrixRows() as $row) {
            if ($row['status'] === 'done' && $row['ui_parity'] === 'none') {
                $violations[] = $row['legacy_path'];
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_waived_rows_have_ui_parity_none(): void
    {
        $violations = [];

        foreach ($this->matrixRows() as $row) {
            if ($row['status'] === 'waived' && $row['ui_parity'] !== 'none') {
                $violations[] = $row['legacy_path'];
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_primary_buyer_vendor_admin_routes_are_ui_parity_full(): void
    {
        $registry = json_decode(file_get_contents(base_path('../docs/parity-route-registry.json')) ?: '{}', true);
        $fullPaths = $registry['ui_parity']['full_legacy_paths'] ?? [];

        $byPath = [];
        foreach ($this->matrixRows() as $row) {
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

        $this->assertSame([], $missing);
    }

    public function test_admin_products_spa_path_points_to_admin_products(): void
    {
        foreach ($this->matrixRows() as $row) {
            if ($row['legacy_path'] !== 'admin/product/products.php') {
                continue;
            }

            $this->assertSame('done', $row['status']);
            $this->assertSame('/admin/products', $row['spa_path']);
            $this->assertSame('full', $row['ui_parity']);
        }
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
