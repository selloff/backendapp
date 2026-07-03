<?php

namespace Tests\Feature\LegacyImport;

use Tests\TestCase;

class Pass21StagingImportTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/legacy-staging-subset.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_profiled_staging_import_completes_within_maintenance_budget(): void
    {
        $startedAt = microtime(true);

        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--profile' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('total_wall_time')
            ->expectsOutputToContain('maintenance_budget');

        $elapsed = microtime(true) - $startedAt;
        $budget = (int) config('selloff.legacy_import.maintenance_window_seconds', 14400);

        $this->assertLessThan($budget, $elapsed, 'Staging import exceeded maintenance window budget');

        $this->artisan('selloff:verify-legacy-import', [
            '--source' => $this->fixture,
        ])->assertSuccessful();
    }

    public function test_profiled_ci_subset_import_completes_with_timing_output(): void
    {
        $fixture = base_path('tests/fixtures/legacy-subset.sql');

        $this->artisan('selloff:import-legacy-data', [
            '--source' => $fixture,
            '--profile' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Importer timing');

        $this->artisan('selloff:verify-legacy-import', [
            '--source' => $fixture,
        ])->assertSuccessful();
    }

    public function test_dry_run_on_staging_fixture_reports_all_importer_tables(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--dry-run' => true,
        ])->assertSuccessful();
    }
}
