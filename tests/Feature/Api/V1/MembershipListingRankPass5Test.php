<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('recommended sort ranks higher visibility multiplier above peer listing', function () {
    $highVendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $lowVendor = User::query()->where('email', 'vendor2@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = rankPlan_in_MembershipListingRankPass5();

    $highProduct = publishedProduct_in_MembershipListingRankPass5($highVendor, $category, 'rank-high-visibility');
    $lowProduct = publishedProduct_in_MembershipListingRankPass5($lowVendor, $category, 'rank-low-visibility');

    $timestamp = now()->subHour();
    Product::query()->whereIn('id', [$highProduct->id, $lowProduct->id])->update(['created_at' => $timestamp]);

    assignPlan_in_MembershipListingRankPass5($highVendor, $plan, visibilityMultiplier: 10);
    assignPlan_in_MembershipListingRankPass5($lowVendor, $plan, visibilityMultiplier: 1);

    $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
        ->assertOk();

    $ids = collect($response->json('data.data'))->pluck('id');

    expect($ids->search($highProduct->id) < $ids->search($lowProduct->id))->toBeTrue();
});

test('recommended sort prioritizes active top boost weight over non boosted peer', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = rankPlan_in_MembershipListingRankPass5();

    $boosted = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-top-boosted');
    $regular = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-regular-listing');

    $timestamp = now()->subHours(2);
    Product::query()->whereIn('id', [$boosted->id, $regular->id])->update([
        'created_at' => $timestamp,
        'is_promoted' => false,
    ]);

    $boosted->forceFill([
        'top_boost_active' => true,
        'top_boost_expires_at' => now()->addDays(5),
        'top_boost_weight' => 250,
        'top_boost_badge_label' => 'Pro TOP+',
    ])->save();

    $regular->forceFill([
        'top_boost_active' => false,
        'top_boost_weight' => 0,
    ])->save();

    assignPlan_in_MembershipListingRankPass5($vendor, $plan, visibilityMultiplier: 1);

    $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
        ->assertOk();

    $ids = collect($response->json('data.data'))->pluck('id');

    expect($ids->first())->toBe($boosted->id);
});

test('expired top boost does not outrank active boosted peer', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = rankPlan_in_MembershipListingRankPass5();

    $expiredBoost = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-expired-top');
    $activeBoost = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-active-top');

    $timestamp = now()->subHours(3);
    Product::query()->whereIn('id', [$expiredBoost->id, $activeBoost->id])->update([
        'created_at' => $timestamp,
        'is_promoted' => false,
    ]);

    $expiredBoost->forceFill([
        'top_boost_active' => true,
        'top_boost_expires_at' => now()->subDay(),
        'top_boost_weight' => 500,
    ])->save();

    $activeBoost->forceFill([
        'top_boost_active' => true,
        'top_boost_expires_at' => now()->addDays(2),
        'top_boost_weight' => 120,
    ])->save();

    assignPlan_in_MembershipListingRankPass5($vendor, $plan, visibilityMultiplier: 1);

    $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
        ->assertOk();

    $ids = collect($response->json('data.data'))->pluck('id');

    expect($ids->search($activeBoost->id) < $ids->search($expiredBoost->id))->toBeTrue();
});

test('promoted vip listing still outranks membership top boost on recommended sort', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = rankPlan_in_MembershipListingRankPass5();

    $vip = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-vip-listing');
    $top = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-top-only');

    Product::query()->whereIn('id', [$vip->id, $top->id])->update([
        'created_at' => now()->subDay(),
    ]);

    $vip->update(['is_promoted' => true]);
    $top->forceFill([
        'is_promoted' => false,
        'top_boost_active' => true,
        'top_boost_expires_at' => now()->addWeek(),
        'top_boost_weight' => 400,
    ])->save();

    assignPlan_in_MembershipListingRankPass5($vendor, $plan, visibilityMultiplier: 1);

    $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
        ->assertOk();

    expect($response->json('data.data.0.id'))->toBe($vip->id);
});

test('price sort ignores membership ranking', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = rankPlan_in_MembershipListingRankPass5();

    $expensiveBoosted = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-expensive-boosted');
    $cheapRegular = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-cheap-regular');

    $expensiveBoosted->forceFill([
        'price' => 90000,
        'top_boost_active' => true,
        'top_boost_expires_at' => now()->addWeek(),
        'top_boost_weight' => 300,
    ])->save();

    $cheapRegular->forceFill([
        'price' => 15000,
        'top_boost_active' => false,
        'top_boost_weight' => 0,
    ])->save();

    assignPlan_in_MembershipListingRankPass5($vendor, $plan, visibilityMultiplier: 10);

    $response = $this->getJson("/api/v1/products?sort=price&direction=asc&category_id={$category->id}&per_page=100")
        ->assertOk();

    $ids = collect($response->json('data.data'))->pluck('id');

    expect($ids->search($cheapRegular->id) < $ids->search($expensiveBoosted->id))->toBeTrue();
});

test('product resource exposes top boost badge fields', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = rankPlan_in_MembershipListingRankPass5(credits: 2);
    $product = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-resource-badge');

    assignPlan_in_MembershipListingRankPass5($vendor, $plan, visibilityMultiplier: 2, creditsRemaining: 2);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
        ->assertOk();

    $this->getJson('/api/v1/products?search=Rank+resource+badge')
        ->assertOk()
        ->assertJsonPath('data.data.0.is_top_boosted', true)
        ->assertJsonPath('data.data.0.top_badge_label', 'Pro TOP+');
});

test('expire membership top boosts command clears active flag', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $product = publishedProduct_in_MembershipListingRankPass5($vendor, $category, 'rank-expire-command');

    $product->forceFill([
        'top_boost_active' => true,
        'top_boost_expires_at' => now()->subHour(),
        'top_boost_weight' => 90,
        'top_boost_badge_label' => 'Pro TOP+',
    ])->save();

    $this->artisan('selloff:expire-membership-top-boosts')->assertSuccessful();

    $product->refresh();
    expect($product->top_boost_active)->toBeFalse();
    expect($product->top_boost_badge_label)->toBeNull();
});

function rankPlan_in_MembershipListingRankPass5(int $credits = 0): MembershipPlan
{
    return MembershipPlan::query()->create([
        'title' => 'Rank Test Plan',
        'price' => 9000,
        'currency_code' => 'NGN',
        'duration_days' => 30,
        'is_active' => true,
        'plan_order' => 97,
        'global_listing_limit' => 50,
        'visibility_multiplier' => 1,
        'top_credits_per_period' => $credits,
        'top_badge_label' => 'Pro TOP+',
        'top_rank_weight' => 200,
    ]);
}

function publishedProduct_in_MembershipListingRankPass5(User $vendor, Category $category, string $slug): Product
{
    $product = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => $slug,
        'price' => 5000,
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

function assignPlan_in_MembershipListingRankPass5(User $vendor, MembershipPlan $plan, float $visibilityMultiplier, int $creditsRemaining = 0): UserMembershipPlan
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
        'entitlements_snapshot' => [
            'plan_id' => $plan->id,
            'plan_title' => $plan->title,
            'global_listing_limit' => 50,
            'visibility_multiplier' => $visibilityMultiplier,
            'top_credits_per_period' => $plan->top_credits_per_period,
            'top_badge_label' => $plan->top_badge_label,
            'top_rank_weight' => $plan->top_rank_weight,
            'category_limits' => [],
        ],
        'top_credits_remaining' => $creditsRemaining,
        'top_credits_period_started_at' => now()->subDay(),
        'top_credits_period_ends_at' => now()->addMonth(),
    ]);
}
