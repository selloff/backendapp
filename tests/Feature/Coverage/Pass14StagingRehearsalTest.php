<?php

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('migration dry run passes on ci fixture', function () {
    expect($this->fixture)->toBeFile();

    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--dry-run' => true,
    ])->assertSuccessful();
});

test('live import and verify pass on ci fixture', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    $this->artisan('selloff:verify-legacy-import', [
        '--source' => $this->fixture,
    ])->assertSuccessful();
});

test('phase 14 runbooks exist', function () {
    foreach ([
        'docs/STAGING_REHEARSAL.md',
        'docs/STAGING_BOOTSTRAP.md',
        'docs/PRODUCTION_DATA_MIGRATION.md',
        'docs/PHASE_14_QA_PRODUCTION.md',
        'docs/PHASE_14_SIGNOFF.md',
        'docs/RESPONSIVE_QA_CHECKLIST.md',
        'scripts/staging-rehearsal.mjs',
        'scripts/staging-rehearsal-deployed.mjs',
        'scripts/rollback-drill.mjs',
        'scripts/production-cutover.mjs',
        'docs/PHASE_23_SIGNOFF.md',
    ] as $relative) {
        expect(monorepo_path($relative))->toBeFile($relative);
    }
});