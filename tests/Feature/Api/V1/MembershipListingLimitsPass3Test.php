<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor cannot publish when global listing limit is reached', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = limitedPlan_in_MembershipListingLimitsPass3(globalLimit: 1, categoryId: $category->id, categoryLimit: 5);

    enableMembershipSystem_in_MembershipListingLimitsPass3();
    assignPlan_in_MembershipListingLimitsPass3($vendor, $plan, globalLimit: 1, categoryId: $category->id, categoryLimit: 5);

    $product = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => 'limit-test-existing',
        'price' => 1000,
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'currency_code' => 'NGN',
    ]);
    $product->translations()->create(['locale' => 'en', 'title' => 'Existing listing']);

    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products', [
        'title' => 'Second listing',
        'category_id' => $category->id,
        'type' => 'physical',
        'listing_type' => 'ordinary_listing',
        'price' => 2000,
        'stock' => 1,
        'status' => 'published',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['membership']);
});

test('vendor can save draft without consuming listing limit', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = limitedPlan_in_MembershipListingLimitsPass3(globalLimit: 1, categoryId: $category->id, categoryLimit: 1);

    enableMembershipSystem_in_MembershipListingLimitsPass3();
    assignPlan_in_MembershipListingLimitsPass3($vendor, $plan, globalLimit: 1, categoryId: $category->id, categoryLimit: 1);

    Sanctum::actingAs($vendor);

    $this->postJson('/api/v1/products', [
        'title' => 'Draft only listing',
        'category_id' => $category->id,
        'type' => 'physical',
        'listing_type' => 'ordinary_listing',
        'price' => 0,
        'stock' => 0,
        'status' => 'draft',
    ])->assertCreated();
});

test('vendor status includes listing usage and can add products flag', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = limitedPlan_in_MembershipListingLimitsPass3(globalLimit: 2, categoryId: $category->id, categoryLimit: 1);

    enableMembershipSystem_in_MembershipListingLimitsPass3();
    assignPlan_in_MembershipListingLimitsPass3($vendor, $plan, globalLimit: 2, categoryId: $category->id, categoryLimit: 1);

    Product::query()
        ->where('vendor_id', $vendor->id)
        ->update(['is_deleted' => true]);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/membership/status')
        ->assertOk()
        ->assertJsonPath('data.membership_enforced', true)
        ->assertJsonPath('data.can_add_products', true)
        ->assertJsonPath('data.listing_usage.global.limit', 2)
        ->assertJsonStructure([
            'data' => [
                'listing_usage' => ['global' => ['used', 'limit', 'remaining'], 'by_category'],
                'entitlements',
            ],
        ]);
});

test('catalog hides products from vendors without active membership when enforced', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = limitedPlan_in_MembershipListingLimitsPass3(globalLimit: 5, categoryId: $category->id, categoryLimit: 5);

    enableMembershipSystem_in_MembershipListingLimitsPass3();
    assignPlan_in_MembershipListingLimitsPass3($vendor, $plan, globalLimit: 5, categoryId: $category->id, categoryLimit: 5);

    $product = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => 'membership-visible-listing',
        'price' => 1500,
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'currency_code' => 'NGN',
    ]);
    $product->translations()->create([
        'locale' => 'en',
        'title' => 'Visible when membership active',
    ]);

    $this->getJson('/api/v1/products?search=Visible+when+membership+active')
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $product->id);

    UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->update(['is_active' => false]);

    $this->getJson('/api/v1/products?search=Visible+when+membership+active')
        ->assertOk()
        ->assertJsonPath('data.data', []);
});

test('deactivate expired memberships command marks subscriptions inactive after grace', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->firstOrFail();

    $subscription = UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subDays(5),
            'is_active' => true,
        ],
    );

    $this->artisan('selloff:deactivate-expired-memberships')->assertSuccessful();

    expect((bool) $subscription->fresh()->is_active)->toBeFalse();
});

function enableMembershipSystem_in_MembershipListingLimitsPass3(): void
{
    app(PlatformSettingsService::class)->upsertMany([
        'membership_plans_system' => true,
    ], 'product');
}

function limitedPlan_in_MembershipListingLimitsPass3(int $globalLimit, int $categoryId, int $categoryLimit): MembershipPlan
{
    return MembershipPlan::query()->create([
        'title' => 'Limit Test Plan',
        'price' => 5000,
        'currency_code' => 'NGN',
        'duration_days' => 30,
        'is_active' => true,
        'plan_order' => 99,
        'global_listing_limit' => $globalLimit,
        'visibility_multiplier' => 1,
        'top_credits_per_period' => 0,
    ]);
}

function assignPlan_in_MembershipListingLimitsPass3(User $vendor, MembershipPlan $plan, int $globalLimit, int $categoryId, int $categoryLimit): void
{
    UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->update(['is_active' => false]);

    UserMembershipPlan::query()->create([
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
        'is_active' => true,
        'entitlements_snapshot' => [
            'plan_id' => $plan->id,
            'plan_title' => $plan->title,
            'global_listing_limit' => $globalLimit,
            'visibility_multiplier' => 1,
            'top_credits_per_period' => 0,
            'category_limits' => [
                (string) $categoryId => $categoryLimit,
            ],
        ],
        'top_credits_remaining' => 0,
    ]);
}
