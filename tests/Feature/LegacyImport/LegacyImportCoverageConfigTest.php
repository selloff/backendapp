<?php

use App\LegacyImport\Importers\RoutesLegacyImporter;
use App\LegacyImport\Support\LegacyImportConfig;

test('coverage excluded tables is separate from importer exclusion', function () {
    $coverage = LegacyImportConfig::coverageExcludedTables();
    $excluded = LegacyImportConfig::excludedImporters();

    expect($coverage)->toContain('ci_sessions');
    expect($coverage)->not->toContain('routes');
    expect($excluded)->toBe([]);
});

test('orchestrator still runs importers for coverage excluded tables', function () {
    $importer = new RoutesLegacyImporter(app(\App\LegacyImport\LegacyImportMapRepository::class));

    expect($importer->legacyTable())->toBe('routes');
    expect(LegacyImportConfig::coverageExcludedTables())->not->toContain('routes');
});

test('legacy skip tables alias still supported', function () {
    config([
        'selloff.legacy_import.coverage_excluded_tables' => null,
        'selloff.legacy_import.skip_tables' => ['legacy_alias_table'],
    ]);

    expect(LegacyImportConfig::coverageExcludedTables())->toBe(['legacy_alias_table']);
});
