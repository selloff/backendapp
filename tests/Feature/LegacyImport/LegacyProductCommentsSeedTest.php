<?php

use App\LegacyImport\Sync\LegacyProductCommentsSync;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('sync imports legacy comments when products exist', function () {
    $vendorId = DB::table('users')->insertGetId([
        'first_name' => 'Legacy',
        'last_name' => 'Vendor',
        'slug' => 'legacy-vendor-comments',
        'username' => 'legacyvendorcomments',
        'email' => 'legacy-vendor-comments@example.test',
        'password' => bcrypt('secret'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ([99, 4178, 9006, 12485] as $productId) {
        DB::table('products')->updateOrInsert(
            ['id' => $productId],
            [
                'vendor_id' => $vendorId,
                'slug' => 'legacy-product-'.$productId,
                'sku' => 'LEGACY-'.$productId,
                'type' => 'physical',
                'listing_type' => 'sell_on_site',
                'status' => 'published',
                'visibility' => 'visible',
                'is_active' => true,
                'legacy_id' => $productId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    $synced = app(LegacyProductCommentsSync::class)->sync();

    expect($synced)->toBeGreaterThanOrEqual(7);
    $this->assertDatabaseHas('comments', [
        'legacy_id' => 1,
        'email' => 'philchima@gmail.com',
        'is_approved' => false,
    ]);
    $this->assertDatabaseHas('comments', [
        'legacy_id' => 5,
        'is_approved' => true,
    ]);
});
