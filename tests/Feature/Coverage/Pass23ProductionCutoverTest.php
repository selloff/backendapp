<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

/**
 * Phase 23 production cutover gates — runbooks, orchestrator, decommission plan.
 */
class Pass23ProductionCutoverTest extends TestCase
{
    public function test_phase_23_runbooks_and_scripts_exist(): void
    {
        foreach ([
            '../docs/PHASE_23_SIGNOFF.md',
            '../docs/LEGACY_DECOMMISSION.md',
            '../docs/PRODUCTION_DATA_MIGRATION.md',
            '../scripts/production-cutover.mjs',
            '../scripts/rollback-drill.mjs',
        ] as $relative) {
            $this->assertFileExists(base_path($relative), $relative);
        }
    }

    public function test_production_cutover_script_supports_all_modes(): void
    {
        $contents = file_get_contents(base_path('../scripts/production-cutover.mjs')) ?: '';

        $this->assertStringContainsString('--pre-flight', $contents);
        $this->assertStringContainsString('--import', $contents);
        $this->assertStringContainsString('--post-cutover', $contents);
        $this->assertStringContainsString('CUTOVER_DUMP', $contents);
        $this->assertStringContainsString('never commit', strtolower($contents));
        $this->assertStringContainsString('selloff:import-legacy-data', $contents);
        $this->assertStringContainsString('selloff:verify-legacy-import', $contents);
        $this->assertStringContainsString('--check-images=100', $contents);
    }

    public function test_phase_23_signoff_covers_cutover_timeline(): void
    {
        $contents = file_get_contents(base_path('../docs/PHASE_23_SIGNOFF.md')) ?: '';

        foreach (['23.1', '23.2', '23.3', '23.4', '23.5', '23.6', '23.7', '23.8'] as $step) {
            $this->assertStringContainsString($step, $contents, "Missing step {$step}");
        }

        $this->assertStringContainsString('production-cutover.mjs', $contents);
        $this->assertStringContainsString('LEGACY_DECOMMISSION.md', $contents);
    }

    public function test_production_migration_doc_links_phase_23_orchestrator(): void
    {
        $contents = file_get_contents(base_path('../docs/PRODUCTION_DATA_MIGRATION.md')) ?: '';

        $this->assertStringContainsString('production-cutover.mjs', $contents);
        $this->assertStringContainsString('PHASE_23_SIGNOFF.md', $contents);
    }

    public function test_legacy_decommission_requires_48h_stability(): void
    {
        $contents = file_get_contents(base_path('../docs/LEGACY_DECOMMISSION.md')) ?: '';

        $this->assertStringContainsString('48h', $contents);
        $this->assertStringContainsString('read-only', strtolower($contents));
    }
}
