<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

/**
 * Phase 14.5–14.6 local rehearsal gate: migration dry-run + live import verify on CI fixture.
 */
class Pass14StagingRehearsalTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_migration_dry_run_passes_on_ci_fixture(): void
    {
        $this->assertFileExists($this->fixture);

        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--dry-run' => true,
        ])->assertSuccessful();
    }

    public function test_live_import_and_verify_pass_on_ci_fixture(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->artisan('selloff:verify-legacy-import', [
            '--source' => $this->fixture,
        ])->assertSuccessful();
    }

    public function test_phase_14_runbooks_exist(): void
    {
        foreach ([
            '../docs/STAGING_REHEARSAL.md',
            '../docs/STAGING_BOOTSTRAP.md',
            '../docs/PRODUCTION_DATA_MIGRATION.md',
            '../docs/PHASE_14_QA_PRODUCTION.md',
            '../docs/PHASE_14_SIGNOFF.md',
            '../docs/RESPONSIVE_QA_CHECKLIST.md',
            '../scripts/staging-rehearsal.mjs',
            '../scripts/staging-rehearsal-deployed.mjs',
            '../scripts/rollback-drill.mjs',
            '../scripts/production-cutover.mjs',
            '../docs/PHASE_23_SIGNOFF.md',
        ] as $relative) {
            $this->assertFileExists(base_path($relative), $relative);
        }
    }
}
