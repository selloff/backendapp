<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

class Pass13RegistryTest extends TestCase
{
    public function test_parity_matrix_has_no_waived_partial_rows(): void
    {
        $contents = file_get_contents(base_path('../docs/spa-parity-matrix.csv')) ?: '';

        $this->assertStringNotContainsString(
            '"waived-partial"',
            $contents,
            'Run node scripts/resolve-waived-partial.mjs after registry updates.',
        );
    }

    public function test_waived_matrix_rows_have_registry_notes(): void
    {
        $matrixPath = base_path('../docs/spa-parity-matrix.csv');
        $registryPath = base_path('../docs/parity-route-registry.json');
        $registry = json_decode(file_get_contents($registryPath) ?: '{}', true);

        $missing = [];
        foreach ($this->matrixRows($matrixPath) as $row) {
            if ($row['status'] !== 'waived') {
                continue;
            }

            if (! $this->hasWaiveNote($row['legacy_path'], $registry)) {
                $missing[] = $row['legacy_path'];
            }
        }

        $this->assertSame([], $missing, 'Every waived matrix row needs a registry note or waive_prefix rule.');
    }

    public function test_promoted_admin_language_and_currency_routes_are_done(): void
    {
        $registry = json_decode(file_get_contents(base_path('../docs/parity-route-registry.json')) ?: '{}', true);
        $done = $registry['done'] ?? [];

        $this->assertSame('/admin/languages', $done['admin/language/translations.php']['spa_path'] ?? null);
        $this->assertSame('/admin/languages', $done['admin/language/edit_language.php']['spa_path'] ?? null);
        $this->assertSame('/admin/currencies', $done['admin/currency/currency_settings.php']['spa_path'] ?? null);
    }

    public function test_font_manager_rows_are_permanently_waived_with_notes(): void
    {
        $registry = json_decode(file_get_contents(base_path('../docs/parity-route-registry.json')) ?: '{}', true);
        $waived = $registry['waived'] ?? [];

        $this->assertNotEmpty($waived['admin/font/fonts.php'] ?? null);
        $this->assertNotEmpty($waived['admin/font/edit.php'] ?? null);
    }

    /**
     * @return list<array{legacy_path: string, status: string}>
     */
    private function matrixRows(string $path): array
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
