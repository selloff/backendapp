<?php

use App\LegacyImport\LegacyImportCoverage;
use App\LegacyImport\LegacyImportOrchestrator;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportConfig;
use App\LegacyImport\Support\LegacyImportMemory;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    LegacyImportMemory::applyConfiguredLimit();

    $this->fixture = base_path('tests/fixtures/legacy-staging-subset.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('staging subset import matches dump row counts', function () {
    $reader = new MySqlDumpReader($this->fixture);

    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    expect(DB::table('users')->count())->toBe($reader->rowCount('users'));
    expect(DB::table('orders')->count())->toBe($reader->rowCount('orders'));

    // Staging subset truncates vendor users; most legacy products are skipped (orphan vendor FK).
    $importedProducts = (int) DB::table('legacy_import_maps')
        ->where('legacy_table', 'products')
        ->count();
    expect(DB::table('products')->count())->toBe($importedProducts);
    expect($importedProducts)->toBeGreaterThan(0);

    $importedImages = (int) DB::table('legacy_import_maps')
        ->where('legacy_table', 'images')
        ->count();
    expect(DB::table('product_images')->count())->toBe($importedImages);
    expect($importedImages)->toBeGreaterThan(0);
});

test('staging subset verify passes with source comparison', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    $this->artisan('selloff:verify-legacy-import', [
        '--source' => $this->fixture,
    ])->assertSuccessful();
});

test('staging subset has zero unhandled tables', function () {
    $reader = new MySqlDumpReader($this->fixture);
    $coverage = app(LegacyImportCoverage::class);
    $orchestrator = app(LegacyImportOrchestrator::class);

    $unhandled = $coverage->unhandledTables(
        $reader,
        $orchestrator->coveredLegacyTables(),
        LegacyImportConfig::coverageExcludedTables(),
    );

    expect($unhandled)->toBe([]);
});

test('full production dump dry run when dump present', function () {
    $dump = monorepo_path('docs/data/production-mysql-dump.sql');
    if (! is_file($dump)) {
        $this->markTestSkipped('Production dump not available at docs/data/production-mysql-dump.sql');

        return;
    }

    ini_set('memory_limit', '512M');

    $this->artisan('selloff:import-legacy-data', [
        '--source' => $dump,
        '--dry-run' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry-run coverage: all dump tables are handled or explicitly skipped.');
});