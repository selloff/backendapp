<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Sync\LegacyProductCommentsSync;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyProductCommentsSeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_sync_imports_legacy_comments_when_products_exist(): void
    {
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

        $this->assertGreaterThanOrEqual(7, $synced);
        $this->assertDatabaseHas('comments', [
            'legacy_id' => 1,
            'email' => 'philchima@gmail.com',
            'is_approved' => false,
        ]);
        $this->assertDatabaseHas('comments', [
            'legacy_id' => 5,
            'is_approved' => true,
        ]);
    }
}
