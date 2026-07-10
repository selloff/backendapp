<?php

use App\LegacyImport\LegacyImportCoverage;
use App\LegacyImport\LegacyImportOrchestrator;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyImportConfig;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('phase 16 subset fixture imports catalog commerce and payment depth', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    expect(DB::table('product_options')->where('legacy_id', 3101)->count())->toBe(1);
    expect(DB::table('product_variants')->where('legacy_id', 3103)->count())->toBe(1);
    expect(DB::table('custom_fields')->where('legacy_id', 3301)->count())->toBe(1);
    expect(DB::table('digital_files')->where('legacy_id', 3401)->count())->toBe(1);
    expect(DB::table('product_license_keys')->where('legacy_id', 3501)->count())->toBe(1);
    expect(DB::table('tags')->where('legacy_id', 3201)->count())->toBe(1);
    expect(DB::table('invoices')->where('legacy_id', 4101)->count())->toBe(1);
    expect(DB::table('quote_requests')->where('legacy_id', 4102)->count())->toBe(1);
    expect(DB::table('digital_sales')->where('legacy_id', 4103)->count())->toBe(1);
    expect(DB::table('coupon_usages')->where('legacy_id', 8102)->count())->toBe(1);
    expect(DB::table('tax_rules')->where('legacy_id', 8201)->count())->toBe(1);
    expect(DB::table('bank_transfer_requests')->where('legacy_id', 8301)->count())->toBe(1);
    expect(DB::table('membership_plans')->where('legacy_id', 8401)->count())->toBe(1);
    expect(DB::table('user_membership_plans')->where('legacy_id', 8402)->count())->toBe(1);
    expect(DB::table('membership_transactions')->where('legacy_id', 8403)->count())->toBe(1);
    expect(DB::table('membership_transactions')->where('legacy_id', 8403)->value('term_months'))->toBe(1);
    expect(DB::table('membership_transactions')->where('legacy_id', 8403)->value('purchase_type'))->toBe('new');
    expect(DB::table('user_membership_plans')->where('legacy_id', 8402)->value('term_months'))->toBe(1);
    expect(DB::table('user_membership_plans')->where('legacy_id', 8402)->value('last_paid_amount'))->not->toBeNull();
    expect(DB::table('promotion_transactions')->where('legacy_id', 8501)->count())->toBe(1);
    expect(DB::table('login_activities')->where('id', 8601)->count())->toBe(1);
});

test('legacy numeric visibility maps to visible string', function () {
    expect(LegacyValueCoercer::visibility(1))->toBe('visible');
    expect(LegacyValueCoercer::visibility(0))->toBe('hidden');
});

test('legacy numeric visibility imports as visible', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    expect(DB::table('products')->where('id', 301)->value('visibility'))->toBe('visible');
});

test('phase 16 verify passes with extended orphan checks', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    $this->artisan('selloff:verify-legacy-import', [
        '--source' => $this->fixture,
    ])->assertSuccessful();
});

test('phase 16 coverage has zero unhandled tables on subset', function () {
    $reader = new MySqlDumpReader($this->fixture);
    $coverage = app(LegacyImportCoverage::class);
    $orchestrator = app(LegacyImportOrchestrator::class);

    $unhandled = $coverage->unhandledTables(
        $reader,
        $orchestrator->coveredLegacyTables(),
        LegacyImportConfig::coverageExcludedTables(),
    );

    expect($unhandled)->toBe([]);
});