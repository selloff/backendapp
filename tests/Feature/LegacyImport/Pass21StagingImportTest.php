<?php

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/legacy-staging-subset.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('profiled staging import completes within maintenance budget', function () {
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

    expect($elapsed)->toBeLessThan($budget, 'Staging import exceeded maintenance window budget');

    $this->artisan('selloff:verify-legacy-import', [
        '--source' => $this->fixture,
    ])->assertSuccessful();
});

test('profiled ci subset import completes with timing output', function () {
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
});

test('dry run on staging fixture reports all importer tables', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--dry-run' => true,
    ])->assertSuccessful();
});