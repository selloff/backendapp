<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\User\Models\VendorProfile;
use App\Services\Platform\PlatformSettingsService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('product detail without membership system hides premium links', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $product = publishedProduct_in_MembershipProductPerksPass7($vendor, $category, 'perks-system-off');

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.vendor.membership_detail_perks.allow_website_link', false)
        ->assertJsonPath('data.vendor.membership_detail_perks.allow_social_links', false)
        ->assertJsonPath('data.vendor.membership_detail_perks.allow_whatsapp_link', false)
        ->assertJsonPath('data.vendor.membership_detail_perks.hide_seller_feedback', false)
        ->assertJsonPath('data.vendor.hide_seller_feedback', false)
        ->assertJsonPath('data.vendor.social_links', []);
});

test('product detail exposes filtered social links for entitled vendor', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $product = publishedProduct_in_MembershipProductPerksPass7($vendor, $category, 'perks-social-links');
    $plan = detailPerksPlan_in_MembershipProductPerksPass7(allowWebsite: true, allowSocial: true, allowWhatsapp: true, hideFeedback: false);

    VendorProfile::query()->where('user_id', $vendor->id)->update([
        'social_media_data' => [
            'facebook' => 'https://facebook.com/selloff-demo',
            'website' => 'demo.selloff.test',
            'whatsapp_url' => 'https://wa.me/2348012345678',
        ],
    ]);

    enableMembershipSystem_in_MembershipProductPerksPass7();
    assignPlan_in_MembershipProductPerksPass7($vendor, $plan, [
        'allow_website_link' => true,
        'allow_social_links' => true,
        'allow_whatsapp_link' => true,
        'hide_seller_feedback' => false,
    ]);

    $response = $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.vendor.membership_detail_perks.allow_website_link', true)
        ->assertJsonPath('data.vendor.membership_detail_perks.allow_social_links', true)
        ->assertJsonPath('data.vendor.membership_detail_perks.allow_whatsapp_link', true)
        ->assertJsonPath('data.vendor.hide_seller_feedback', false);

    $types = collect($response->json('data.vendor.social_links'))->pluck('type')->all();
    expect($types)->toBe(['website', 'facebook', 'whatsapp']);
    expect($response->json('data.vendor.social_links.0.url'))->toBe('https://demo.selloff.test');
});

test('product detail hides social links when perks are disabled on plan', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $product = publishedProduct_in_MembershipProductPerksPass7($vendor, $category, 'perks-free-plan');
    $plan = detailPerksPlan_in_MembershipProductPerksPass7();

    VendorProfile::query()->where('user_id', $vendor->id)->update([
        'social_media_data' => [
            'facebook' => 'https://facebook.com/selloff-demo',
            'website' => 'https://demo.selloff.test',
        ],
    ]);

    enableMembershipSystem_in_MembershipProductPerksPass7();
    assignPlan_in_MembershipProductPerksPass7($vendor, $plan);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.vendor.social_links', []);
});

test('hide seller feedback perk suppresses public feedback endpoints', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $product = publishedProduct_in_MembershipProductPerksPass7($vendor, $category, 'perks-hide-feedback');
    $plan = detailPerksPlan_in_MembershipProductPerksPass7(hideFeedback: true);

    Feedback::query()
        ->where('vendor_id', $vendor->id)
        ->update(['moderation_status' => 'approved']);

    enableMembershipSystem_in_MembershipProductPerksPass7();
    assignPlan_in_MembershipProductPerksPass7($vendor, $plan, ['hide_seller_feedback' => true]);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.vendor.hide_seller_feedback', true)
        ->assertJsonPath('data.vendor.membership_detail_perks.hide_seller_feedback', true);

    $slug = $vendor->vendorProfile?->slug;
    expect($slug)->not->toBeEmpty();

    $this->getJson("/api/v1/vendors/{$slug}/feedback")
        ->assertOk()
        ->assertJsonPath('data.total', 0)
        ->assertJsonPath('data.data', []);

    $this->getJson("/api/v1/vendors/{$slug}/feedback/summary")
        ->assertOk()
        ->assertJsonPath('data.total_count', 0)
        ->assertJsonPath('data.percent_positive', 0);
});

test('product listing does not include membership detail perks', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $product = publishedProduct_in_MembershipProductPerksPass7($vendor, $category, 'perks-list-hidden');

    enableMembershipSystem_in_MembershipProductPerksPass7();
    assignPlan_in_MembershipProductPerksPass7($vendor, detailPerksPlan_in_MembershipProductPerksPass7(allowWebsite: true, allowSocial: true), [
        'allow_website_link' => true,
        'allow_social_links' => true,
    ]);

    $this->getJson('/api/v1/products?search='.urlencode((string) $product->translations()->value('title')))
        ->assertOk()
        ->assertJsonMissingPath('data.data.0.vendor.membership_detail_perks')
        ->assertJsonMissingPath('data.data.0.vendor.social_links')
        ->assertJsonMissingPath('data.data.0.vendor.hide_seller_feedback');
});

test('whatsapp link falls back to vendor phone when url missing', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $product = publishedProduct_in_MembershipProductPerksPass7($vendor, $category, 'perks-whatsapp-phone');
    $plan = detailPerksPlan_in_MembershipProductPerksPass7(allowWhatsapp: true);

    $vendor->forceFill(['phone_number' => '+234 801 234 5678'])->save();
    VendorProfile::query()->where('user_id', $vendor->id)->update([
        'social_media_data' => [],
    ]);

    enableMembershipSystem_in_MembershipProductPerksPass7();
    assignPlan_in_MembershipProductPerksPass7($vendor, $plan, ['allow_whatsapp_link' => true]);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.vendor.social_links.0.type', 'whatsapp')
        ->assertJsonPath('data.vendor.social_links.0.url', 'https://wa.me/2348012345678');
});

function detailPerksPlan_in_MembershipProductPerksPass7(bool $allowWebsite = false, bool $allowSocial = false, bool $allowWhatsapp = false, bool $hideFeedback = false): MembershipPlan
{
    return MembershipPlan::query()->create([
        'title' => 'Detail Perks Plan',
        'price' => 15000,
        'currency_code' => 'NGN',
        'duration_days' => 30,
        'is_active' => true,
        'plan_order' => 97,
        'global_listing_limit' => 50,
        'visibility_multiplier' => 2,
        'allow_website_link' => $allowWebsite,
        'allow_social_links' => $allowSocial,
        'allow_whatsapp_link' => $allowWhatsapp,
        'hide_seller_feedback' => $hideFeedback,
    ]);
}

function publishedProduct_in_MembershipProductPerksPass7(User $vendor, Category $category, string $slug): Product
{
    $product = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => $slug,
        'price' => 3200,
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'currency_code' => 'NGN',
    ]);
    $product->translations()->create([
        'locale' => 'en',
        'title' => ucfirst(str_replace('-', ' ', $slug)),
    ]);

    return $product;
}

/**
 * @param  array<string, mixed>  $snapshotOverrides
 */
function assignPlan_in_MembershipProductPerksPass7(User $vendor, MembershipPlan $plan, array $snapshotOverrides = []): UserMembershipPlan
{
    UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->update(['is_active' => false]);

    return UserMembershipPlan::query()->create([
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
        'is_active' => true,
        'entitlements_snapshot' => array_merge([
            'plan_id' => $plan->id,
            'plan_title' => $plan->title,
            'global_listing_limit' => 50,
            'visibility_multiplier' => 2,
            'category_limits' => [],
            'allow_website_link' => (bool) $plan->allow_website_link,
            'allow_social_links' => (bool) $plan->allow_social_links,
            'allow_whatsapp_link' => (bool) $plan->allow_whatsapp_link,
            'hide_seller_feedback' => (bool) $plan->hide_seller_feedback,
        ], $snapshotOverrides),
        'top_credits_remaining' => 0,
        'top_credits_period_started_at' => now()->subDay(),
        'top_credits_period_ends_at' => now()->addMonth(),
    ]);
}

function enableMembershipSystem_in_MembershipProductPerksPass7(): void
{
    app(PlatformSettingsService::class)->upsertMany([
        'membership_plans_system' => true,
    ], 'product');
}
