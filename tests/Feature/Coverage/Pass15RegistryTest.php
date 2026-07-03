<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

class Pass15RegistryTest extends TestCase
{
    public function test_parity_matrix_has_no_waived_partial_rows(): void
    {
        $contents = file_get_contents(base_path('../docs/spa-parity-matrix.csv')) ?: '';

        $this->assertStringNotContainsString('"waived-partial"', $contents);
    }

    public function test_waived_matrix_rows_have_registry_notes(): void
    {
        $registry = $this->loadRegistry();
        $missing = [];

        foreach ($this->matrixRows() as $row) {
            if ($row['status'] !== 'waived') {
                continue;
            }

            if (! $this->hasWaiveNote($row['legacy_path'], $registry)) {
                $missing[] = $row['legacy_path'];
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_done_rows_never_have_ui_parity_none(): void
    {
        $violations = [];

        foreach ($this->matrixRows() as $row) {
            if ($row['status'] === 'done' && $row['ui_parity'] === 'none') {
                $violations[] = $row['legacy_path'];
            }
        }

        $this->assertSame([], $violations, 'Run node scripts/sync-ui-parity.mjs');
    }

    public function test_waived_rows_have_empty_spa_path(): void
    {
        $violations = [];

        foreach ($this->matrixRows() as $row) {
            if ($row['status'] === 'waived' && $row['spa_path'] !== '') {
                $violations[] = $row['legacy_path'].' → '.$row['spa_path'];
            }
        }

        $this->assertSame([], $violations, 'Run node scripts/sync-parity-matrix-status.mjs');
    }

    public function test_no_legacy_path_in_both_done_and_waived_registry_maps(): void
    {
        $registry = $this->loadRegistry();
        $done = $registry['done'] ?? [];
        $waived = $registry['waived'] ?? [];

        $conflicts = [];
        foreach (array_keys($done) as $legacyPath) {
            if (isset($waived[$legacyPath])) {
                $conflicts[] = $legacyPath;
            }
        }

        $this->assertSame([], $conflicts);
    }

    public function test_phase15_promoted_paths_are_done_in_matrix(): void
    {
        $byPath = [];
        foreach ($this->matrixRows() as $row) {
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
            $this->assertNotNull($row, $legacyPath);
            $this->assertSame('done', $row['status'], $legacyPath);
            $this->assertSame($spaPath, $row['spa_path'], $legacyPath);
            $this->assertSame('full', $row['ui_parity'], $legacyPath);
        }
    }

    public function test_auto_waive_remaining_pending_is_disabled(): void
    {
        $registry = $this->loadRegistry();

        $this->assertFalse($registry['auto_waive_remaining_pending'] ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRegistry(): array
    {
        return json_decode(file_get_contents(base_path('../docs/parity-route-registry.json')) ?: '{}', true);
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

    /**
     * @param  array<string, mixed>  $registry
     */
    private function hasWaiveNote(string $legacyPath, array $registry): bool
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
}
