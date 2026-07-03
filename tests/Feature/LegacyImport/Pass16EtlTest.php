<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\LegacyImportCoverage;
use App\LegacyImport\LegacyImportOrchestrator;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportConfig;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Pass16EtlTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_phase_16_subset_fixture_imports_catalog_commerce_and_payment_depth(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('product_options')->where('legacy_id', 3101)->count());
        $this->assertSame(1, DB::table('product_variants')->where('legacy_id', 3103)->count());
        $this->assertSame(1, DB::table('custom_fields')->where('legacy_id', 3301)->count());
        $this->assertSame(1, DB::table('digital_files')->where('legacy_id', 3401)->count());
        $this->assertSame(1, DB::table('product_license_keys')->where('legacy_id', 3501)->count());
        $this->assertSame(1, DB::table('tags')->where('legacy_id', 3201)->count());
        $this->assertSame(1, DB::table('invoices')->where('legacy_id', 4101)->count());
        $this->assertSame(1, DB::table('quote_requests')->where('legacy_id', 4102)->count());
        $this->assertSame(1, DB::table('digital_sales')->where('legacy_id', 4103)->count());
        $this->assertSame(1, DB::table('coupon_usages')->where('legacy_id', 8102)->count());
        $this->assertSame(1, DB::table('tax_rules')->where('legacy_id', 8201)->count());
        $this->assertSame(1, DB::table('bank_transfer_requests')->where('legacy_id', 8301)->count());
        $this->assertSame(1, DB::table('membership_plans')->where('legacy_id', 8401)->count());
        $this->assertSame(1, DB::table('user_membership_plans')->where('legacy_id', 8402)->count());
        $this->assertSame(1, DB::table('membership_transactions')->where('legacy_id', 8403)->count());
        $this->assertSame(1, DB::table('membership_transactions')->where('legacy_id', 8403)->value('term_months'));
        $this->assertSame('new', DB::table('membership_transactions')->where('legacy_id', 8403)->value('purchase_type'));
        $this->assertSame(1, DB::table('user_membership_plans')->where('legacy_id', 8402)->value('term_months'));
        $this->assertNotNull(DB::table('user_membership_plans')->where('legacy_id', 8402)->value('last_paid_amount'));
        $this->assertSame(1, DB::table('promotion_transactions')->where('legacy_id', 8501)->count());
        $this->assertSame(1, DB::table('login_activities')->where('id', 8601)->count());
    }

    public function test_legacy_numeric_visibility_maps_to_visible_string(): void
    {
        $this->assertSame('visible', LegacyValueCoercer::visibility(1));
        $this->assertSame('hidden', LegacyValueCoercer::visibility(0));
    }

    public function test_legacy_numeric_visibility_imports_as_visible(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->assertSame('visible', DB::table('products')->where('id', 301)->value('visibility'));
    }

    public function test_phase_16_verify_passes_with_extended_orphan_checks(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => $this->fixture,
        ])->assertSuccessful();

        $this->artisan('selloff:verify-legacy-import', [
            '--source' => $this->fixture,
        ])->assertSuccessful();
    }

    public function test_phase_16_coverage_has_zero_unhandled_tables_on_subset(): void
    {
        $reader = new MySqlDumpReader($this->fixture);
        $coverage = app(LegacyImportCoverage::class);
        $orchestrator = app(LegacyImportOrchestrator::class);

        $unhandled = $coverage->unhandledTables(
            $reader,
            $orchestrator->coveredLegacyTables(),
            LegacyImportConfig::coverageExcludedTables(),
        );

        $this->assertSame([], $unhandled);
    }
}
