<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Content\Models\AdSpace;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Promotion\Models\Coupon;
use App\Modules\Selloff\Promotion\Models\CouponUsage;
use App\Modules\Selloff\User\Models\Follower;
use App\Modules\Selloff\User\Models\VendorProfile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassIBacklogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_save_shop_policies_and_public_shop_exposes_them(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->putJson('/api/v1/vendors/me/profile', [
            'shop_policies' => '<p>Free returns within 14 days.</p>',
        ])
            ->assertOk()
            ->assertJsonPath('data.shop_policies', '<p>Free returns within 14 days.</p>');

        $slug = VendorProfile::query()->where('user_id', $vendor->id)->value('slug');

        $this->getJson('/api/v1/vendors/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.shop_policies', '<p>Free returns within 14 days.</p>');
    }

    public function test_vendor_can_update_rss_and_vat_shop_settings(): void
    {
        app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
            'rss_enabled' => true,
            'vat_status' => true,
            'vendors_change_shop_name' => true,
        ]);

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        Sanctum::actingAs($vendor);

        $this->putJson('/api/v1/vendors/me/profile', [
            'show_rss_feeds' => true,
            'is_fixed_vat' => true,
            'fixed_vat_rate' => 7.5,
        ])
            ->assertOk()
            ->assertJsonPath('data.show_rss_feeds', true)
            ->assertJsonPath('data.is_fixed_vat', true)
            ->assertJsonPath('data.fixed_vat_rate', '7.50')
            ->assertJsonPath('data.can_edit_shop_name', true);

        $this->assertTrue($vendor->fresh()->show_rss_feeds);
    }

    public function test_vendor_cannot_change_shop_name_when_platform_disallows_it(): void
    {
        app(\App\Services\Platform\PlatformSettingsService::class)->upsertMany([
            'vendors_change_shop_name' => false,
        ]);

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $originalShopName = VendorProfile::query()->where('user_id', $vendor->id)->value('shop_name');
        Sanctum::actingAs($vendor);

        $this->putJson('/api/v1/vendors/me/profile', [
            'shop_name' => 'Renamed Shop',
            'about_me' => 'Updated description',
        ])
            ->assertOk()
            ->assertJsonPath('data.shop_name', $originalShopName)
            ->assertJsonPath('data.about_me', 'Updated description')
            ->assertJsonPath('data.can_edit_shop_name', false);
    }

    public function test_admin_can_manage_membership_ad_spaces_and_theme(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/membership-plans', [
            'title' => 'Pass I Pro',
            'price' => 5000,
            'duration_days' => 30,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Pass I Pro');

        $this->postJson('/api/v1/admin/cms/ad-spaces', [
            'ad_space_key' => 'sidebar_top',
            'title' => 'Sidebar',
            'is_active' => true,
        ])->assertCreated();

        $this->putJson('/api/v1/admin/theme', [
            'primary_color' => '#1d4ed8',
            'font_family' => 'Inter',
        ], $this->superAdminPinHeaders())
            ->assertOk()
            ->assertJsonPath('data.primary_color', '#1d4ed8');

        $this->getJson('/api/v1/ad-spaces/sidebar_top')->assertOk();
    }

    public function test_admin_can_bulk_import_categories(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $before = Category::query()->count();

        $this->postJson('/api/v1/admin/categories/bulk', [
            'categories' => [
                ['name' => 'Pass I Category A'],
                ['name' => 'Pass I Category B'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_count', 2);

        $this->assertSame($before + 2, Category::query()->count());
    }

    public function test_buyer_coupons_downloads_reviews_and_follow_flow(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/users/'.$vendor->id.'/follow')->assertCreated();
        $this->assertGreaterThanOrEqual(1, Follower::query()->where('follower_id', $buyer->id)->count());

        $slug = $vendor->vendorProfile?->slug ?? $vendor->slug;
        $this->getJson('/api/v1/vendors/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.vendor.is_following', true);

        $this->getJson('/api/v1/account/following')->assertOk();
        $this->getJson('/api/v1/account/coupons')->assertOk();
        $this->getJson('/api/v1/account/downloads')->assertOk();
        $this->getJson('/api/v1/account/reviews')->assertOk();
    }

    public function test_vendor_can_subscribe_to_membership_plan(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = MembershipPlan::query()->firstOrFail();

        Sanctum::actingAs($vendor);

        $this->postJson('/api/v1/membership-plans/'.$plan->id.'/subscribe')
            ->assertCreated()
            ->assertJsonPath('data.is_active', true);

        $this->getJson('/api/v1/account/membership')->assertOk();
    }

    public function test_demo_seed_includes_pass_i_sample_data(): void
    {
        $this->assertGreaterThan(0, AdSpace::query()->count());
        $this->assertGreaterThan(0, MembershipPlan::query()->count());
        $this->assertGreaterThan(0, CouponUsage::query()->count());
        $this->assertGreaterThan(0, DigitalSale::query()->count());
    }
}
