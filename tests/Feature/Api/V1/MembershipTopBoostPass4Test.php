<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTopApplication;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('vendor can apply top boost and credits decrement', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = topPlan_in_MembershipTopBoostPass4();
    $product = publishedProduct_in_MembershipTopBoostPass4($vendor, $category, 'top-boost-target');

    assignPlan_in_MembershipTopBoostPass4($vendor, $plan, creditsRemaining: 3, rankWeight: 180, badgeLabel: 'Pro TOP+');

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/membership/top-credits')
        ->assertOk()
        ->assertJsonPath('data.remaining', 3)
        ->assertJsonPath('data.per_period_allowance', 5)
        ->assertJsonPath('data.badge_label', 'Pro TOP+')
        ->assertJsonPath('data.rank_weight', 180)
        ->assertJsonPath('data.default_duration_days', 7);

    $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost", [
        'duration_days' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.top_credits.remaining', 2)
        ->assertJsonPath('data.product.top_boost_active', true)
        ->assertJsonPath('data.product.top_boost_weight', 180)
        ->assertJsonPath('data.badge_label', 'Pro TOP+')
        ->assertJsonPath('data.application.credits_consumed', 1);

    $product->refresh();
    expect($product->top_boost_active)->toBeTrue();
    expect((int) $product->top_boost_weight)->toBe(180);
    expect($product->top_boost_expires_at)->not->toBeNull();

    $this->assertDatabaseHas('membership_top_applications', [
        'product_id' => $product->id,
        'credits_consumed' => 1,
    ]);

    expect((int) UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->where('is_active', true)
        ->value('top_credits_remaining'))->toBe(2);
});

test('vendor cannot apply top boost without remaining credits', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = topPlan_in_MembershipTopBoostPass4();
    $product = publishedProduct_in_MembershipTopBoostPass4($vendor, $category, 'top-boost-no-credits');

    assignPlan_in_MembershipTopBoostPass4($vendor, $plan, creditsRemaining: 0);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['top_credits']);
});

test('vendor cannot apply top boost to draft listing', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = topPlan_in_MembershipTopBoostPass4();

    $product = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => 'top-boost-draft',
        'price' => 1000,
        'status' => 'draft',
        'visibility' => 'visible',
        'is_active' => true,
        'is_draft' => true,
        'is_deleted' => false,
        'currency_code' => 'NGN',
    ]);

    assignPlan_in_MembershipTopBoostPass4($vendor, $plan, creditsRemaining: 2);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product']);
});

test('vendor cannot apply top boost when listing already boosted', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = topPlan_in_MembershipTopBoostPass4();
    $product = publishedProduct_in_MembershipTopBoostPass4($vendor, $category, 'top-boost-active');

    $subscription = assignPlan_in_MembershipTopBoostPass4($vendor, $plan, creditsRemaining: 2);

    $product->forceFill([
        'top_boost_active' => true,
        'top_boost_expires_at' => now()->addDays(3),
        'top_boost_weight' => 120,
    ])->save();

    MembershipTopApplication::query()->create([
        'user_membership_plan_id' => $subscription->id,
        'product_id' => $product->id,
        'credits_consumed' => 1,
        'applied_at' => now()->subDay(),
        'expires_at' => now()->addDays(3),
    ]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['top_boost']);
});

test('top boost expiry is capped by membership period end', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = topPlan_in_MembershipTopBoostPass4();
    $product = publishedProduct_in_MembershipTopBoostPass4($vendor, $category, 'top-boost-cap');

    $periodEnd = now()->addDays(2);
    assignPlan_in_MembershipTopBoostPass4($vendor, $plan, creditsRemaining: 1, periodEndsAt: $periodEnd);

    Sanctum::actingAs($vendor);

    $response = $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost", [
        'duration_days' => 14,
    ])->assertOk();

    $expiresAt = $response->json('data.product.top_boost_expires_at');
    expect($expiresAt)->not->toBeNull();
    expect(now()->parse($expiresAt)->lte($periodEnd->copy()->addSecond()))->toBeTrue();
});

test('custom default top boost duration is used when request omits duration', function () {
    app(PlatformSettingsService::class)->upsertMany([
        'membership_top_boost_duration_days' => 10,
    ], 'product');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = topPlan_in_MembershipTopBoostPass4();
    $product = publishedProduct_in_MembershipTopBoostPass4($vendor, $category, 'top-boost-default-duration');

    assignPlan_in_MembershipTopBoostPass4($vendor, $plan, creditsRemaining: 1);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/membership/top-credits')
        ->assertOk()
        ->assertJsonPath('data.default_duration_days', 10);

    $response = $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
        ->assertOk();

    $expiresAt = now()->parse((string) $response->json('data.product.top_boost_expires_at'));
    expect($expiresAt->between(now()->addDays(9), now()->addDays(11)))->toBeTrue();
});

function topPlan_in_MembershipTopBoostPass4(): MembershipPlan
{
    return MembershipPlan::query()->create([
        'title' => 'TOP Test Plan',
        'price' => 12000,
        'currency_code' => 'NGN',
        'duration_days' => 30,
        'is_active' => true,
        'plan_order' => 98,
        'global_listing_limit' => 50,
        'visibility_multiplier' => 3,
        'top_credits_per_period' => 5,
        'top_badge_label' => 'Pro TOP+',
        'top_rank_weight' => 180,
    ]);
}

function publishedProduct_in_MembershipTopBoostPass4(User $vendor, Category $category, string $slug): Product
{
    $product = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => $slug,
        'price' => 2500,
        'status' => 'published',
        'visibility' => 'visible',
        'is_active' => true,
        'is_draft' => false,
        'is_deleted' => false,
        'currency_code' => 'NGN',
    ]);
    $product->translations()->create(['locale' => 'en', 'title' => ucfirst(str_replace('-', ' ', $slug))]);

    return $product;
}

function assignPlan_in_MembershipTopBoostPass4(User $vendor, MembershipPlan $plan, int $creditsRemaining, int $rankWeight = 180, ?string $badgeLabel = 'Pro TOP+', ?\Illuminate\Support\Carbon $periodEndsAt = null): UserMembershipPlan
{
    UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->update(['is_active' => false]);

    $periodEndsAt ??= now()->addMonth();

    return UserMembershipPlan::query()->create([
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'starts_at' => now()->subDay(),
        'expires_at' => $periodEndsAt,
        'is_active' => true,
        'entitlements_snapshot' => [
            'plan_id' => $plan->id,
            'plan_title' => $plan->title,
            'global_listing_limit' => 50,
            'visibility_multiplier' => 3,
            'top_credits_per_period' => 5,
            'top_badge_label' => $badgeLabel,
            'top_rank_weight' => $rankWeight,
            'category_limits' => [],
        ],
        'top_credits_remaining' => $creditsRemaining,
        'top_credits_period_started_at' => now()->subDay(),
        'top_credits_period_ends_at' => $periodEndsAt,
    ]);
}
