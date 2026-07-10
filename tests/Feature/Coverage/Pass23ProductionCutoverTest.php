<?php

test('phase 23 runbooks and scripts exist', function () {
    foreach ([
        'docs/PHASE_23_SIGNOFF.md',
        'docs/LEGACY_DECOMMISSION.md',
        'docs/PRODUCTION_DATA_MIGRATION.md',
        'scripts/production-cutover.mjs',
        'scripts/rollback-drill.mjs',
    ] as $relative) {
        expect(monorepo_path($relative))->toBeFile($relative);
    }
});

test('production cutover script supports all modes', function () {
    $contents = file_get_contents(monorepo_path('scripts/production-cutover.mjs')) ?: '';

    $this->assertStringContainsString('--pre-flight', $contents);
    $this->assertStringContainsString('--import', $contents);
    $this->assertStringContainsString('--post-cutover', $contents);
    $this->assertStringContainsString('CUTOVER_DUMP', $contents);
    $this->assertStringContainsString('never commit', strtolower($contents));
    $this->assertStringContainsString('selloff:import-legacy-data', $contents);
    $this->assertStringContainsString('selloff:verify-legacy-import', $contents);
    $this->assertStringContainsString('--check-images=100', $contents);
});

test('phase 23 signoff covers cutover timeline', function () {
    $contents = file_get_contents(monorepo_path('docs/PHASE_23_SIGNOFF.md')) ?: '';

    foreach (['23.1', '23.2', '23.3', '23.4', '23.5', '23.6', '23.7', '23.8'] as $step) {
        $this->assertStringContainsString($step, $contents, "Missing step {$step}");
    }

    $this->assertStringContainsString('production-cutover.mjs', $contents);
    $this->assertStringContainsString('LEGACY_DECOMMISSION.md', $contents);
});

test('production migration doc links phase 23 orchestrator', function () {
    $contents = file_get_contents(monorepo_path('docs/PRODUCTION_DATA_MIGRATION.md')) ?: '';

    $this->assertStringContainsString('production-cutover.mjs', $contents);
    $this->assertStringContainsString('PHASE_23_SIGNOFF.md', $contents);
});

test('legacy decommission requires 48h stability', function () {
    $contents = file_get_contents(monorepo_path('docs/LEGACY_DECOMMISSION.md')) ?: '';

    $this->assertStringContainsString('48h', $contents);
    $this->assertStringContainsString('read-only', strtolower($contents));
});
