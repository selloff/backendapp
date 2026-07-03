<?php

namespace Tests\Feature\LegacyImport;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Pass10LegacyImportTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_dry_run_reports_expected_counts_without_writes(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, User::query()->where('email', 'legacy-buyer@test.import')->count());
        $this->assertSame(0, DB::table('legacy_import_maps')->count());
    }

    public function test_live_import_from_fixture_populates_database(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->assertSame(3, User::query()->whereIn('email', [
            'legacy-admin@test.import',
            'legacy-vendor@test.import',
            'legacy-buyer@test.import',
        ])->count());

        $this->assertSame(1, Product::query()->where('sku', 'LEGACY-PHONE-1')->count());
        $this->assertSame(26500.00, (float) Order::query()->where('order_number', 900001)->value('price_total'));
        $this->assertGreaterThanOrEqual(8, DB::table('legacy_import_maps')->count());
    }

    public function test_verify_legacy_import_passes_after_import(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->artisan('selloff:verify-legacy-import', [
            '--source' => $this->fixture,
        ])->assertSuccessful();
    }
}
