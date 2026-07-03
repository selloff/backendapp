<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\LegacyImportCoverage;
use App\LegacyImport\LegacyImportOrchestrator;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportConfig;
use App\LegacyImport\Support\LegacyImportMemory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Pass17StagingEtlTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        LegacyImportMemory::applyConfiguredLimit();

        $this->fixture = base_path('tests/fixtures/legacy-staging-subset.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_staging_subset_import_matches_dump_row_counts(): void
    {
        $reader = new MySqlDumpReader($this->fixture);

        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->assertSame($reader->rowCount('users'), DB::table('users')->count());
        $this->assertSame($reader->rowCount('orders'), DB::table('orders')->count());

        // Staging subset truncates vendor users; most legacy products are skipped (orphan vendor FK).
        $importedProducts = (int) DB::table('legacy_import_maps')
            ->where('legacy_table', 'products')
            ->count();
        $this->assertSame($importedProducts, DB::table('products')->count());
        $this->assertGreaterThan(0, $importedProducts);

        $importedImages = (int) DB::table('legacy_import_maps')
            ->where('legacy_table', 'images')
            ->count();
        $this->assertSame($importedImages, DB::table('product_images')->count());
        $this->assertGreaterThan(0, $importedImages);
    }

    public function test_staging_subset_verify_passes_with_source_comparison(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->artisan('selloff:verify-legacy-import', [
            '--source' => $this->fixture,
        ])->assertSuccessful();
    }

    public function test_staging_subset_has_zero_unhandled_tables(): void
    {
        $reader = new MySqlDumpReader($this->fixture);
        $coverage = app(LegacyImportCoverage::class);
        $orchestrator = app(LegacyImportOrchestrator::class);

        $unhandled = $coverage->unhandledTables(
            $reader,
            $orchestrator->coveredLegacyTables(),
            LegacyImportConfig::coverageExcludedTables(),
        );

        $this->assertSame([], $unhandled);
    }

    public function test_full_production_dump_dry_run_when_dump_present(): void
    {
        $dump = base_path('../docs/data/production-mysql-dump.sql');
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
    }
}
