<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Pass18EtlTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_dry_run_imports_location_and_wallet_tables_without_writes(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, DB::table('countries')->count());
        $this->assertSame(0, DB::table('wallet_deposits')->count());
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame(0, DB::table('legacy_import_maps')->count());
    }

    public function test_live_import_populates_location_and_wallet_tables(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('countries')->where('legacy_id', 601)->count());
        $this->assertSame(1, DB::table('states')->where('legacy_id', 602)->count());
        $this->assertSame(1, DB::table('cities')->where('legacy_id', 603)->count());
        $this->assertSame(1, DB::table('shipping_addresses')->where('legacy_id', 901)->count());
        $this->assertSame(1, DB::table('wallet_deposits')->where('legacy_id', 1001)->count());
        $this->assertSame(1, DB::table('wallet_transactions')->where('legacy_id', 1002)->count());
        $this->assertSame(5000.00, (float) DB::table('wallet_deposits')->where('legacy_id', 1001)->value('amount'));
        $this->assertSame(26500.00, (float) DB::table('wallet_transactions')->where('legacy_id', 1002)->value('amount'));
        $this->assertSame(22500.00, (float) DB::table('vendor_earnings')->where('legacy_id', 1101)->value('earned_amount'));
    }

    public function test_verify_legacy_import_passes_wallet_and_earnings_checks(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->artisan('selloff:verify-legacy-import', [
            '--source' => $this->fixture,
        ])->assertSuccessful();
    }

    public function test_profile_flag_reports_importer_timing(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--dry-run' => true,
            '--profile' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Importer timing (Pass 21 profile):');
    }
}
