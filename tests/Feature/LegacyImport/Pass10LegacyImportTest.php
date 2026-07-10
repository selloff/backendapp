<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('dry run reports expected counts without writes', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(User::query()->where('email', 'legacy-buyer@test.import')->count())->toBe(0);
    expect(DB::table('legacy_import_maps')->count())->toBe(0);
});

test('live import from fixture populates database', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    expect(User::query()->whereIn('email', [
        'legacy-admin@test.import',
        'legacy-vendor@test.import',
        'legacy-buyer@test.import',
    ])->count())->toBe(3);

    expect(Product::query()->where('sku', 'LEGACY-PHONE-1')->count())->toBe(1);
    expect((float) Order::query()->where('order_number', 900001)->value('price_total'))->toBe(26500.00);
    expect(DB::table('legacy_import_maps')->count())->toBeGreaterThanOrEqual(8);
});

test('verify legacy import passes after import', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => $this->fixture,
    ])->assertSuccessful();

    $this->artisan('selloff:verify-legacy-import', [
        '--source' => $this->fixture,
    ])->assertSuccessful();
});