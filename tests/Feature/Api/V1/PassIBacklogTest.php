<?php

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

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor can save shop policies and public shop exposes them', function () {
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
});

test('vendor can update rss and vat shop settings', function () {
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

    expect($vendor->fresh()->show_rss_feeds)->toBeTrue();
});

test('vendor cannot change shop name when platform disallows it', function () {
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
});

test('admin can manage membership ad spaces and theme', function () {
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
});

test('admin can bulk import categories', function () {
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

    expect(Category::query()->count())->toBe($before + 2);
});

test('buyer coupons downloads reviews and follow flow', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/users/'.$vendor->id.'/follow')->assertCreated();
    expect(Follower::query()->where('follower_id', $buyer->id)->count())->toBeGreaterThanOrEqual(1);

    $slug = $vendor->vendorProfile?->slug ?? $vendor->slug;
    $this->getJson('/api/v1/vendors/'.$slug)
        ->assertOk()
        ->assertJsonPath('data.vendor.is_following', true);

    $this->getJson('/api/v1/account/following')->assertOk();
    $this->getJson('/api/v1/account/coupons')->assertOk();
    $this->getJson('/api/v1/account/downloads')->assertOk();
    $this->getJson('/api/v1/account/reviews')->assertOk();
});

test('vendor can subscribe to membership plan', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->firstOrFail();

    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/membership-plans/'.$plan->id.'/subscribe')
        ->assertCreated()
        ->assertJsonPath('data.is_active', true);

    $this->getJson('/api/v1/account/membership')->assertOk();
});

test('demo seed includes pass i sample data', function () {
    expect(AdSpace::query()->count())->toBeGreaterThan(0);
    expect(MembershipPlan::query()->count())->toBeGreaterThan(0);
    expect(CouponUsage::query()->count())->toBeGreaterThan(0);
    expect(DigitalSale::query()->count())->toBeGreaterThan(0);
});
