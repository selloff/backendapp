<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductDepthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_can_show_product_with_images_and_custom_fields(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonStructure([
                'data' => [
                    'title',
                    'slug',
                    'sku',
                    'type',
                    'listing_type',
                    'price',
                    'stock',
                    'images',
                    'options',
                    'category_breadcrumb',
                    'vendor' => ['shop_name'],
                ],
            ]);
    }

    public function test_admin_can_search_and_filter_products_with_pagination(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/products?q=DEMO-PHONE-1&per_page=15&page=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'data',
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.total'));
    }

    public function test_admin_reject_stores_reason_and_featured_filter_works(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/products/'.$pending->id.'/reject', [
            'reason' => 'Images do not meet guidelines.',
        ])
            ->assertOk()
            ->assertJsonPath('data.reject_reason', 'Images do not meet guidelines.')
            ->assertJsonPath('data.is_rejected', true);

        $featured = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $featured->update([
            'is_promoted' => true,
            'promote_plan' => 'Daily',
            'promoted_at' => now(),
            'promoted_until' => now()->addDays(7),
        ]);

        $response = $this->getJson('/api/v1/admin/products?list=featured&q=DEMO-PHONE-1&per_page=15')
            ->assertOk();

        $this->assertTrue(
            collect($response->json('data.data'))->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-PHONE-1'),
        );
        $this->assertSame('Daily', $response->json('data.data.0.promote_plan'));
        $this->assertGreaterThanOrEqual(0, $response->json('data.data.0.promotion_remaining_days'));
    }

    public function test_admin_can_add_and_remove_featured_product(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $product->update([
            'is_promoted' => false,
            'promoted_at' => null,
            'promoted_until' => null,
            'promote_plan' => null,
        ]);

        $response = $this->postJson('/api/v1/admin/products/'.$product->id.'/featured', ['days' => 14])
            ->assertOk()
            ->assertJsonPath('data.is_promoted', true);

        $this->assertGreaterThanOrEqual(13, $response->json('data.promotion_remaining_days'));

        $this->deleteJson('/api/v1/admin/products/'.$product->id.'/featured')
            ->assertOk()
            ->assertJsonPath('data.is_promoted', false);
    }

    public function test_admin_can_add_and_remove_special_offer_product(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $product->update([
            'is_special_offer' => false,
            'special_offer_at' => null,
        ]);

        $this->postJson('/api/v1/admin/products/'.$product->id.'/special-offer')
            ->assertOk()
            ->assertJsonPath('data.is_special_offer', true);

        $this->getJson('/api/v1/admin/products?list=special&q=DEMO-PHONE-1&per_page=15')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'DEMO-PHONE-1']);

        $this->deleteJson('/api/v1/admin/products/'.$product->id.'/special-offer')
            ->assertOk()
            ->assertJsonPath('data.is_special_offer', false);
    }

    public function test_admin_approve_clears_reject_reason_and_edited_flag(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
        $pending->update(['is_edited' => true]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/products/'.$pending->id.'/reject', [
            'reason' => 'Temporary rejection.',
        ])->assertOk();

        $this->postJson('/api/v1/admin/products/'.$pending->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.reject_reason', null)
            ->assertJsonPath('data.is_edited', false);
    }

    public function test_admin_approved_product_appears_on_items_for_sale_list(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
        $pending->update([
            'is_verified' => false,
            'is_edited' => false,
            'is_draft' => false,
            'is_deleted' => false,
            'status' => 'draft',
            'visibility' => 'hidden',
        ]);
        Sanctum::actingAs($admin);

        $this->assertFalse(
            collect($this->getJson('/api/v1/admin/products?list=all&q=DEMO-PENDING-1')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-PENDING-1'),
        );

        $this->postJson('/api/v1/admin/products/'.$pending->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.visibility', 'visible');

        $this->assertTrue(
            collect($this->getJson('/api/v1/admin/products?list=all&q=DEMO-PENDING-1')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-PENDING-1'),
        );
    }

    public function test_admin_can_filter_edited_products_and_bulk_approve(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
        $product->update(['is_edited' => true, 'is_verified' => false]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/products?list=edited')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/admin/products/bulk', [
            'action' => 'approve',
            'product_ids' => [$product->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_edited' => false,
            'is_verified' => true,
        ]);
    }

    public function test_admin_can_export_products_csv_and_update_product(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->get('/api/v1/admin/products/export?q=DEMO-PHONE-1')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->get('/api/v1/admin/products/export?q=DEMO-PHONE-1&format=xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml; charset=UTF-8');

        $this->get('/api/v1/admin/products/export?q=DEMO-PHONE-1&format=excel')
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $this->putJson('/api/v1/admin/products/'.$product->id, [
            'title' => 'Admin Updated Phone',
            'price' => 99999,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Admin Updated Phone');
    }

    public function test_admin_can_filter_pending_hidden_sold_and_draft_products(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
        $pending->update(['is_verified' => false, 'is_edited' => false, 'is_draft' => false, 'is_deleted' => false]);

        $this->getJson('/api/v1/admin/products?list=pending&q=DEMO-PENDING-1')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            collect($this->getJson('/api/v1/admin/products?list=pending&q=DEMO-PENDING-1')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-PENDING-1'),
        );

        $hidden = Product::query()->firstOrCreate(
            ['vendor_id' => $vendor->id, 'sku' => 'DEMO-HIDDEN-1'],
            [
                'category_id' => $pending->category_id,
                'slug' => 'demo-hidden-product',
                'type' => 'physical',
                'listing_type' => 'sell_on_site',
                'status' => 'published',
                'visibility' => 'hidden',
                'is_active' => true,
                'is_verified' => true,
                'price' => 45000,
                'currency_code' => 'NGN',
                'stock' => 5,
            ],
        );
        $hidden->update(['visibility' => 'hidden', 'is_verified' => true, 'is_draft' => false, 'is_deleted' => false]);

        $this->assertTrue(
            collect($this->getJson('/api/v1/admin/products?list=hidden&q=DEMO-HIDDEN-1')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-HIDDEN-1'),
        );

        $sold = Product::query()->where('sku', 'DEMO-TAB-1')->firstOrFail();
        $sold->update(['is_sold' => true, 'is_deleted' => false]);

        $this->assertTrue(
            collect($this->getJson('/api/v1/admin/products?list=sold&q=DEMO-TAB-1')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-TAB-1'),
        );

        $draft = Product::query()->firstOrCreate(
            ['vendor_id' => $vendor->id, 'sku' => 'DEMO-DRAFT-1'],
            [
                'category_id' => $pending->category_id,
                'slug' => 'demo-draft-product',
                'type' => 'physical',
                'listing_type' => 'sell_on_site',
                'status' => 'draft',
                'visibility' => 'hidden',
                'is_active' => false,
                'is_verified' => false,
                'price' => 12000,
                'currency_code' => 'NGN',
                'stock' => 1,
            ],
        );
        $draft->update(['is_draft' => true, 'is_deleted' => false]);

        $this->assertTrue(
            collect($this->getJson('/api/v1/admin/products?list=drafts&q=DEMO-DRAFT-1')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-DRAFT-1'),
        );
    }

    public function test_admin_can_filter_expired_vendor_products(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-PHONE-1')->firstOrFail();
        $plan = \App\Modules\Selloff\Payment\Models\MembershipPlan::query()
            ->where('title', 'Demo Vendor Pro')
            ->firstOrFail();

        Sanctum::actingAs($admin);

        \App\Modules\Selloff\Payment\Models\UserMembershipPlan::query()->updateOrCreate(
            ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
            ['is_active' => true, 'expires_at' => now()->addDays(30)],
        );

        $this->assertFalse(
            collect($this->getJson('/api/v1/admin/products?list=expired&q=DEMO-PHONE-1')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-PHONE-1'),
        );

        \App\Modules\Selloff\Payment\Models\UserMembershipPlan::query()->updateOrCreate(
            ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
            ['is_active' => true, 'expires_at' => now()->subDay()],
        );

        $product->update(['is_deleted' => false, 'is_draft' => false]);

        $this->assertTrue(
            collect($this->getJson('/api/v1/admin/products?list=expired&q=DEMO-PHONE-1')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-PHONE-1'),
        );
    }

    public function test_admin_can_filter_deleted_products_restore_and_delete_permanently(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-LAPTOP-2')->firstOrFail();
        Sanctum::actingAs($admin);

        $product->update(['is_deleted' => true, 'is_active' => false]);
        $productId = $product->id;

        $this->assertTrue(
            collect($this->getJson('/api/v1/admin/products?list=deleted&q=DEMO-LAPTOP-2')->json('data.data'))
                ->contains(fn (array $row) => ($row['sku'] ?? '') === 'DEMO-LAPTOP-2'),
        );

        $this->postJson('/api/v1/admin/products/bulk', [
            'action' => 'restore',
            'product_ids' => [$productId],
        ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'is_deleted' => false,
            'is_active' => true,
        ]);

        $product->update(['is_deleted' => true, 'is_active' => false]);

        $this->postJson('/api/v1/admin/products/bulk', [
            'action' => 'delete_permanently',
            'product_ids' => [$productId],
        ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1);

        $this->assertDatabaseMissing('products', ['id' => $productId]);
    }
}
