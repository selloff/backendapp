<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('dry run imports location and wallet tables without writes', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(DB::table('countries')->count())->toBe(0);
    expect(DB::table('wallet_deposits')->count())->toBe(0);
    expect(DB::table('wallet_transactions')->count())->toBe(0);
    expect(DB::table('legacy_import_maps')->count())->toBe(0);
});

test('live import populates location and wallet tables', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    expect(DB::table('countries')->where('legacy_id', 601)->count())->toBe(1);
    expect(DB::table('states')->where('legacy_id', 602)->count())->toBe(1);
    expect(DB::table('cities')->where('legacy_id', 603)->count())->toBe(1);
    expect(DB::table('shipping_addresses')->where('legacy_id', 901)->count())->toBe(1);
    expect(DB::table('wallet_deposits')->where('legacy_id', 1001)->count())->toBe(1);
    expect(DB::table('wallet_transactions')->where('legacy_id', 1002)->count())->toBe(1);
    expect((float) DB::table('wallet_deposits')->where('legacy_id', 1001)->value('amount'))->toBe(5000.00);
    expect((float) DB::table('wallet_transactions')->where('legacy_id', 1002)->value('amount'))->toBe(26500.00);
    expect((float) DB::table('vendor_earnings')->where('legacy_id', 1101)->value('earned_amount'))->toBe(22500.00);
});

test('verify legacy import passes wallet and earnings checks', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    $this->artisan('selloff:verify-legacy-import', [
        '--source' => $this->fixture,
    ])->assertSuccessful();
});

test('profile flag reports importer timing', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--dry-run' => true,
        '--profile' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Importer timing (Pass 21 profile):');
});