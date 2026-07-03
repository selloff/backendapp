<?php

namespace Tests\Feature\Schema;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Promotion\Models\Coupon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Pass2MigrationTest extends TestCase
{
    public function test_pass2_schema_migrations_and_demo_seed(): void
    {
        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true])
            ->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasColumn('products', 'is_deleted'));
        $this->assertTrue(Schema::hasColumn('products', 'is_draft'));
        $this->assertTrue(Schema::hasColumn('products', 'is_special_offer'));
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('legacy_import_maps'));
        $this->assertTrue(Schema::hasTable('vendor_profiles'));
        $this->assertTrue(Schema::hasTable('escrow_transactions'));

        $this->assertGreaterThanOrEqual(40, Product::query()->count());
        $this->assertDatabaseHas('vendor_profiles', ['shop_name' => 'Demo Electronics']);
        $this->assertDatabaseHas('vendor_profiles', ['shop_name' => 'Demo Fashion Hub']);
        $this->assertDatabaseHas('vendor_profiles', ['shop_name' => 'Lagos Home & Living']);
        $this->assertDatabaseHas('coupons', ['coupon_code' => 'DEMO10']);
        $this->assertGreaterThanOrEqual(
            14,
            Product::query()->whereHas('vendor', fn ($q) => $q->where('email', 'vendor@selloff.test'))->count(),
        );
        $this->assertGreaterThanOrEqual(
            8,
            Product::query()->whereHas('vendor', fn ($q) => $q->where('email', 'vendor2@selloff.test'))->count(),
        );
        $this->assertGreaterThanOrEqual(6, User::role('vendor')->count());
        $this->assertSame(1, Coupon::query()->where('coupon_code', 'DEMO10')->count());
        $this->assertGreaterThanOrEqual(2, \App\Modules\Selloff\Order\Models\Order::query()->count());
    }
}
