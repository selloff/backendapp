<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

class Pass11CoverageManifestTest extends TestCase
{
    public function test_legacy_coverage_manifest_has_row_per_matrix_entry(): void
    {
        $matrixPath = base_path('../docs/spa-parity-matrix.csv');
        $manifestPath = base_path('../docs/LEGACY_COVERAGE_MANIFEST.csv');

        $this->assertFileExists($matrixPath);
        $this->assertFileExists($manifestPath);

        $matrixRows = $this->countDataRows($matrixPath);
        $manifestRows = $this->countDataRows($manifestPath);

        $this->assertSame($matrixRows, $manifestRows, 'Manifest row count must match parity matrix');
    }

    public function test_parity_matrix_has_no_pending_rows_after_sync(): void
    {
        $matrixPath = base_path('../docs/spa-parity-matrix.csv');
        $contents = file_get_contents($matrixPath) ?: '';

        $this->assertStringNotContainsString('"pending"', $contents, 'Run node scripts/sync-parity-matrix-status.mjs');
    }

    public function test_parity_route_registry_auto_waive_disabled(): void
    {
        $path = base_path('../docs/parity-route-registry.json');
        $registry = json_decode(file_get_contents($path) ?: '{}', true);

        $this->assertFalse(
            $registry['auto_waive_remaining_pending'] ?? true,
            'Pass A: disable auto_waive_remaining_pending in parity-route-registry.json'
        );
    }

    public function test_waived_manifest_rows_have_notes(): void
    {
        $manifestPath = base_path('../docs/LEGACY_COVERAGE_MANIFEST.csv');
        $this->assertFileExists($manifestPath);

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

        $this->assertSame([], $missing, 'Waived rows must have manifest notes. Run: node scripts/generate-legacy-coverage-manifest.mjs');
    }

    public function test_parity_route_registry_exists(): void
    {
        $path = base_path('../docs/parity-route-registry.json');
        $this->assertFileExists($path);

        $registry = json_decode(file_get_contents($path) ?: '{}', true);
        $this->assertIsArray($registry['done'] ?? null);
        $this->assertGreaterThan(50, count($registry['done'] ?? []), 'Pass A should expand consolidated done mappings');
    }

    private function countDataRows(string $path): int
    {
        $lines = array_filter(explode("\n", trim(str_replace("\r", '', file_get_contents($path) ?: ''))));

        return max(0, count($lines) - 1);
    }
}
