<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

/**
 * Phase 22 staging rehearsal gates — deployed runbooks, load targets, rollback drill.
 */
class Pass22StagingRehearsalTest extends TestCase
{
    public function test_phase_22_runbooks_and_scripts_exist(): void
    {
        foreach ([
            '../docs/STAGING_REHEARSAL.md',
            '../docs/PRODUCTION_DATA_MIGRATION.md',
            '../docs/PHASE_14_SIGNOFF.md',
            '../scripts/staging-rehearsal-deployed.mjs',
            '../scripts/rollback-drill.mjs',
            '../scripts/load-test/catalog-checkout.mjs',
        ] as $relative) {
            $this->assertFileExists(base_path($relative), $relative);
        }
    }

    public function test_load_test_script_enforces_p95_targets(): void
    {
        $contents = file_get_contents(base_path('../scripts/load-test/catalog-checkout.mjs')) ?: '';

        $this->assertStringContainsString('--enforce', $contents);
        $this->assertStringContainsString('CATALOG_P95_MS', $contents);
        $this->assertStringContainsString('CHECKOUT_P95_MS', $contents);
        $this->assertStringContainsString('500', $contents);
        $this->assertStringContainsString('2000', $contents);
    }

    public function test_staging_rehearsal_script_includes_phase_22_gates(): void
    {
        $local = file_get_contents(base_path('../scripts/staging-rehearsal.mjs')) ?: '';
        $deployed = file_get_contents(base_path('../scripts/staging-rehearsal-deployed.mjs')) ?: '';

        $this->assertStringContainsString('Pass22StagingRehearsalTest', $local);
        $this->assertStringContainsString('staging-rehearsal-deployed.mjs', $local);
        $this->assertStringContainsString('rollback-drill.mjs', $deployed);
        $this->assertStringContainsString('Pass10SecurityAuditTest', $deployed);
        $this->assertStringContainsString('test:e2e:staging', $deployed);
    }

    public function test_rollback_drill_documents_thirty_minute_budget(): void
    {
        $contents = file_get_contents(base_path('../scripts/rollback-drill.mjs')) ?: '';

        $this->assertStringContainsString('30 * 60 * 1000', $contents);
        $this->assertStringContainsString('--timed', $contents);
    }

    public function test_staging_rehearsal_doc_covers_phase_22_deployed_flow(): void
    {
        $contents = file_get_contents(base_path('../docs/STAGING_REHEARSAL.md')) ?: '';

        $this->assertStringContainsString('staging-rehearsal-deployed.mjs', $contents);
        $this->assertStringContainsString('rollback-drill.mjs', $contents);
        $this->assertStringContainsString('test:e2e:staging', $contents);
    }
}
