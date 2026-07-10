<?php

test('phase 22 runbooks and scripts exist', function () {
    foreach ([
        'docs/STAGING_REHEARSAL.md',
        'docs/PRODUCTION_DATA_MIGRATION.md',
        'docs/PHASE_14_SIGNOFF.md',
        'scripts/staging-rehearsal-deployed.mjs',
        'scripts/rollback-drill.mjs',
        'scripts/load-test/catalog-checkout.mjs',
    ] as $relative) {
        expect(monorepo_path($relative))->toBeFile($relative);
    }
});

test('load test script enforces p95 targets', function () {
    $contents = file_get_contents(monorepo_path('scripts/load-test/catalog-checkout.mjs')) ?: '';

    $this->assertStringContainsString('--enforce', $contents);
    $this->assertStringContainsString('CATALOG_P95_MS', $contents);
    $this->assertStringContainsString('CHECKOUT_P95_MS', $contents);
    $this->assertStringContainsString('500', $contents);
    $this->assertStringContainsString('2000', $contents);
});

test('staging rehearsal script includes phase 22 gates', function () {
    $local = file_get_contents(monorepo_path('scripts/staging-rehearsal.mjs')) ?: '';
    $deployed = file_get_contents(monorepo_path('scripts/staging-rehearsal-deployed.mjs')) ?: '';

    $this->assertStringContainsString('Pass22StagingRehearsalTest', $local);
    $this->assertStringContainsString('staging-rehearsal-deployed.mjs', $local);
    $this->assertStringContainsString('rollback-drill.mjs', $deployed);
    $this->assertStringContainsString('Pass10SecurityAuditTest', $deployed);
    $this->assertStringContainsString('test:e2e:staging', $deployed);
});

test('rollback drill documents thirty minute budget', function () {
    $contents = file_get_contents(monorepo_path('scripts/rollback-drill.mjs')) ?: '';

    $this->assertStringContainsString('30 * 60 * 1000', $contents);
    $this->assertStringContainsString('--timed', $contents);
});

test('staging rehearsal doc covers phase 22 deployed flow', function () {
    $contents = file_get_contents(monorepo_path('docs/STAGING_REHEARSAL.md')) ?: '';

    $this->assertStringContainsString('staging-rehearsal-deployed.mjs', $contents);
    $this->assertStringContainsString('rollback-drill.mjs', $contents);
    $this->assertStringContainsString('test:e2e:staging', $contents);
});
