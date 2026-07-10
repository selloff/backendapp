<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Promotion\Models\Coupon;
use Illuminate\Support\Facades\Schema;

test('pass2 schema migrations and demo seed', function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true])
        ->assertExitCode(0);

    expect(Schema::hasTable('products'))->toBeTrue();
    expect(Schema::hasColumn('products', 'is_deleted'))->toBeTrue();
    expect(Schema::hasColumn('products', 'is_draft'))->toBeTrue();
    expect(Schema::hasColumn('products', 'is_special_offer'))->toBeTrue();
    expect(Schema::hasTable('orders'))->toBeTrue();
    expect(Schema::hasTable('legacy_import_maps'))->toBeTrue();
    expect(Schema::hasTable('vendor_profiles'))->toBeTrue();
    expect(Schema::hasTable('escrow_transactions'))->toBeTrue();

    expect(Product::query()->count())->toBeGreaterThanOrEqual(40);
    $this->assertDatabaseHas('vendor_profiles', ['shop_name' => 'Demo Electronics']);
    $this->assertDatabaseHas('vendor_profiles', ['shop_name' => 'Demo Fashion Hub']);
    $this->assertDatabaseHas('vendor_profiles', ['shop_name' => 'Lagos Home & Living']);
    $this->assertDatabaseHas('coupons', ['coupon_code' => 'DEMO10']);
    expect(Product::query()->whereHas('vendor', fn ($q) => $q->where('email', 'vendor@selloff.test'))->count())->toBeGreaterThanOrEqual(14);
    expect(Product::query()->whereHas('vendor', fn ($q) => $q->where('email', 'vendor2@selloff.test'))->count())->toBeGreaterThanOrEqual(8);
    expect(User::role('vendor')->count())->toBeGreaterThanOrEqual(6);
    expect(Coupon::query()->where('coupon_code', 'DEMO10')->count())->toBe(1);
    expect(\App\Modules\Selloff\Order\Models\Order::query()->count())->toBeGreaterThanOrEqual(2);
});
