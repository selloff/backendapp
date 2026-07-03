<?php

namespace Tests\Feature\Coverage;

use Tests\TestCase;

/**
 * Wave 5 pre-cutover ops — orchestrator, runbooks, F1–F7 + E5–E7 scaffolds.
 */
class Wave5PreCutoverOpsTest extends TestCase
{
    public function test_wave5_orchestrator_and_doc_exist(): void
    {
        foreach ([
            '../scripts/wave-5-rehearsal.mjs',
            '../docs/PHASE_WAVE5_PRE_CUTOVER_OPS.md',
            '../docs/STAGING_REHEARSAL.md',
            '../docs/PHASE_23_SIGNOFF.md',
            '../docs/RESPONSIVE_QA_CHECKLIST.md',
        ] as $relative) {
            $this->assertFileExists(base_path($relative), $relative);
        }
    }

    public function test_wave5_script_wires_local_and_deployed_flows(): void
    {
        $wave5 = file_get_contents(base_path('../scripts/wave-5-rehearsal.mjs')) ?: '';

        $this->assertStringContainsString('staging-rehearsal.mjs', $wave5);
        $this->assertStringContainsString('staging-rehearsal-deployed.mjs', $wave5);
        $this->assertStringContainsString('rollback-drill.mjs', $wave5);
        $this->assertStringContainsString('staging-etl-rehearsal.mjs', $wave5);
        $this->assertStringContainsString('catalog-checkout.mjs', $wave5);
        $this->assertStringContainsString('test:e2e:staging', $wave5);
        $this->assertStringContainsString('production-cutover.mjs', $wave5);
        $this->assertStringContainsString('--deployed', $wave5);
        $this->assertStringContainsString('--with-rollback-timed', $wave5);
    }

    public function test_wave5_doc_tracks_f_and_e_exit_gates(): void
    {
        $doc = file_get_contents(base_path('../docs/PHASE_WAVE5_PRE_CUTOVER_OPS.md')) ?: '';

        foreach (['F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'E5', 'E6', 'E7'] as $gate) {
            $this->assertStringContainsString($gate, $doc, "Missing {$gate} in wave 5 doc");
        }

        $this->assertStringContainsString('PHASE_23_SIGNOFF.md', $doc);
        $this->assertStringContainsString('RESPONSIVE_QA_CHECKLIST.md', $doc);
    }

    public function test_visual_regression_and_production_capture_scaffolds_exist(): void
    {
        foreach ([
            '../app.selloff/e2e/visual-regression.spec.ts',
            '../app.selloff/e2e/capture-production-baseline.spec.ts',
            '../app.selloff/e2e/staging-smoke.spec.ts',
            '../docs/PHASE_14_VISUAL_REGRESSION.md',
        ] as $relative) {
            $this->assertFileExists(base_path($relative), $relative);
        }

        $pkg = json_decode(file_get_contents(base_path('../app.selloff/package.json')) ?: '{}', true);
        $scripts = $pkg['scripts'] ?? [];
        $this->assertArrayHasKey('test:e2e:staging', $scripts);
        $this->assertArrayHasKey('test:e2e:production', $scripts);
    }
}
