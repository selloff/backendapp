<?php

test('wave5 orchestrator and doc exist', function () {
    foreach ([
        'scripts/wave-5-rehearsal.mjs',
        'docs/PHASE_WAVE5_PRE_CUTOVER_OPS.md',
        'docs/STAGING_REHEARSAL.md',
        'docs/PHASE_23_SIGNOFF.md',
        'docs/RESPONSIVE_QA_CHECKLIST.md',
    ] as $relative) {
        expect(monorepo_path($relative))->toBeFile($relative);
    }
});

test('wave5 script wires local and deployed flows', function () {
    $wave5 = file_get_contents(monorepo_path('scripts/wave-5-rehearsal.mjs')) ?: '';

    $this->assertStringContainsString('staging-rehearsal.mjs', $wave5);
    $this->assertStringContainsString('staging-rehearsal-deployed.mjs', $wave5);
    $this->assertStringContainsString('rollback-drill.mjs', $wave5);
    $this->assertStringContainsString('staging-etl-rehearsal.mjs', $wave5);
    $this->assertStringContainsString('catalog-checkout.mjs', $wave5);
    $this->assertStringContainsString('test:e2e:staging', $wave5);
    $this->assertStringContainsString('production-cutover.mjs', $wave5);
    $this->assertStringContainsString('--deployed', $wave5);
    $this->assertStringContainsString('--with-rollback-timed', $wave5);
});

test('wave5 doc tracks f and e exit gates', function () {
    $doc = file_get_contents(monorepo_path('docs/PHASE_WAVE5_PRE_CUTOVER_OPS.md')) ?: '';

    foreach (['F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'E5', 'E6', 'E7'] as $gate) {
        $this->assertStringContainsString($gate, $doc, "Missing {$gate} in wave 5 doc");
    }

    $this->assertStringContainsString('PHASE_23_SIGNOFF.md', $doc);
    $this->assertStringContainsString('RESPONSIVE_QA_CHECKLIST.md', $doc);
});

test('visual regression and production capture scaffolds exist', function () {
    foreach ([
        'app.selloff/e2e/visual-regression.spec.ts',
        'app.selloff/e2e/capture-production-baseline.spec.ts',
        'app.selloff/e2e/staging-smoke.spec.ts',
        'docs/PHASE_14_VISUAL_REGRESSION.md',
    ] as $relative) {
        expect(monorepo_path($relative))->toBeFile($relative);
    }

    $pkg = json_decode(file_get_contents(monorepo_path('app.selloff/package.json')) ?: '{}', true);
    $scripts = $pkg['scripts'] ?? [];
    expect($scripts)->toHaveKey('test:e2e:staging');
    expect($scripts)->toHaveKey('test:e2e:production');
});
