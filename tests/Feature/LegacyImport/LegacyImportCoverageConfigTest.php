<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\RoutesLegacyImporter;
use App\LegacyImport\Support\LegacyImportConfig;
use Tests\TestCase;

class LegacyImportCoverageConfigTest extends TestCase
{
    public function test_coverage_excluded_tables_is_separate_from_importer_exclusion(): void
    {
        $coverage = LegacyImportConfig::coverageExcludedTables();
        $excluded = LegacyImportConfig::excludedImporters();

        $this->assertContains('ci_sessions', $coverage);
        $this->assertNotContains('routes', $coverage);
        $this->assertSame([], $excluded);
    }

    public function test_orchestrator_still_runs_importers_for_coverage_excluded_tables(): void
    {
        $importer = new RoutesLegacyImporter(app(\App\LegacyImport\LegacyImportMapRepository::class));

        $this->assertSame('routes', $importer->legacyTable());
        $this->assertNotContains('routes', LegacyImportConfig::coverageExcludedTables());
    }

    public function test_legacy_skip_tables_alias_still_supported(): void
    {
        config([
            'selloff.legacy_import.coverage_excluded_tables' => null,
            'selloff.legacy_import.skip_tables' => ['legacy_alias_table'],
        ]);

        $this->assertSame(['legacy_alias_table'], LegacyImportConfig::coverageExcludedTables());
    }
}
