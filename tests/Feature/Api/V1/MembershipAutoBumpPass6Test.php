<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipAutoBumpService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

afterEach(function () {
    Carbon::setTestNow();

});

test('auto bump command refreshes due published listings', function () {
    Carbon::setTestNow('2026-06-28 12:00:00');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = autoBumpPlan_in_MembershipAutoBumpPass6(intervalHours: 24);

    $due = publishedProduct_in_MembershipAutoBumpPass6($vendor, $category, 'auto-bump-due');
    $fresh = publishedProduct_in_MembershipAutoBumpPass6($vendor, $category, 'auto-bump-fresh');
    $draft = draftProduct_in_MembershipAutoBumpPass6($vendor, $category, 'auto-bump-draft');

    $due->forceFill(['last_bumped_at' => now()->subHours(30)])->save();
    $fresh->forceFill(['last_bumped_at' => now()->subHours(2)])->save();

    enableMembershipSystem_in_MembershipAutoBumpPass6();
    assignPlan_in_MembershipAutoBumpPass6($vendor, $plan, intervalHours: 24);

    $this->artisan('selloff:membership-auto-bump')->assertSuccessful();

    expect($due->fresh()->last_bumped_at?->equalTo(now()))->toBeTrue();
    expect($fresh->fresh()->last_bumped_at?->equalTo(now()->subHours(2)))->toBeTrue();
    expect($draft->fresh()->last_bumped_at)->toBeNull();
});

test('auto bump initializes last bumped at for never bumped listings', function () {
    Carbon::setTestNow('2026-06-28 15:00:00');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = autoBumpPlan_in_MembershipAutoBumpPass6(intervalHours: 12);
    $product = publishedProduct_in_MembershipAutoBumpPass6($vendor, $category, 'auto-bump-first');

    enableMembershipSystem_in_MembershipAutoBumpPass6();
    assignPlan_in_MembershipAutoBumpPass6($vendor, $plan, intervalHours: 12);

    $this->artisan('selloff:membership-auto-bump')->assertSuccessful();

    expect($product->fresh()->last_bumped_at?->equalTo(now()))->toBeTrue();
});

test('auto bump skips vendors without interval entitlement', function () {
    Carbon::setTestNow('2026-06-28 16:00:00');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = autoBumpPlan_in_MembershipAutoBumpPass6(intervalHours: null);
    $product = publishedProduct_in_MembershipAutoBumpPass6($vendor, $category, 'auto-bump-disabled');

    enableMembershipSystem_in_MembershipAutoBumpPass6();
    assignPlan_in_MembershipAutoBumpPass6($vendor, $plan, intervalHours: null);

    $result = app(MembershipAutoBumpService::class)->run();

    expect($result['vendors_processed'])->toBe(0);
    expect($result['products_bumped'])->toBe(0);
    expect($product->fresh()->last_bumped_at)->toBeNull();
});

test('auto bump does not run when membership system is disabled', function () {
    Carbon::setTestNow('2026-06-28 17:00:00');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = autoBumpPlan_in_MembershipAutoBumpPass6(intervalHours: 6);
    $product = publishedProduct_in_MembershipAutoBumpPass6($vendor, $category, 'auto-bump-system-off');

    assignPlan_in_MembershipAutoBumpPass6($vendor, $plan, intervalHours: 6);

    $result = app(MembershipAutoBumpService::class)->run();

    expect($result['products_bumped'])->toBe(0);
    expect($product->fresh()->last_bumped_at)->toBeNull();
});

test('bumped listing ranks above stale peer on recommended sort', function () {
    Carbon::setTestNow('2026-06-28 18:00:00');

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = autoBumpPlan_in_MembershipAutoBumpPass6(intervalHours: 24);

    $stale = publishedProduct_in_MembershipAutoBumpPass6($vendor, $category, 'auto-bump-rank-stale');
    $fresh = publishedProduct_in_MembershipAutoBumpPass6($vendor, $category, 'auto-bump-rank-fresh');

    $timestamp = now()->subDays(3);
    Product::query()->whereIn('id', [$stale->id, $fresh->id])->update([
        'created_at' => $timestamp,
        'is_promoted' => false,
    ]);

    $stale->forceFill(['last_bumped_at' => $timestamp])->save();
    $fresh->forceFill(['last_bumped_at' => now()])->save();

    enableMembershipSystem_in_MembershipAutoBumpPass6();
    assignPlan_in_MembershipAutoBumpPass6($vendor, $plan, intervalHours: 24, visibilityMultiplier: 1);

    $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
        ->assertOk();

    expect($response->json('data.data.0.id'))->toBe($fresh->id);
});

test('vendor entitlements payload includes auto bump settings', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = autoBumpPlan_in_MembershipAutoBumpPass6(intervalHours: 48);

    assignPlan_in_MembershipAutoBumpPass6($vendor, $plan, intervalHours: 48, visibilityMultiplier: 2);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/membership/entitlements')
        ->assertOk()
        ->assertJsonPath('data.auto_bump.enabled', true)
        ->assertJsonPath('data.auto_bump.interval_hours', 48);
});

function enableMembershipSystem_in_MembershipAutoBumpPass6(): void
{
    app(PlatformSettingsService::class)->upsertMany([
        'membership_plans_system' => true,
    ], 'product');
}

function autoBumpPlan_in_MembershipAutoBumpPass6(?int $intervalHours): MembershipPlan
{
    return MembershipPlan::query()->create([
        'title' => 'Auto Bump Plan',
        'price' => 8000,
        'currency_code' => 'NGN',
        'duration_days' => 30,
        'is_active' => true,
        'plan_order' => 96,
        'global_listing_limit' => 50,
        'visibility_multiplier' => 1,
        'auto_bump_interval_hours' => $intervalHours,
        'top_credits_per_period' => 0,
    ]);
}

function publishedProduct_in_MembershipAutoBumpPass6(User $vendor, Category $category, string $slug): Product
{
    $product = Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => $slug,
        'price' => 4000,
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

function draftProduct_in_MembershipAutoBumpPass6(User $vendor, Category $category, string $slug): Product
{
    return Product::query()->create([
        'vendor_id' => $vendor->id,
        'category_id' => $category->id,
        'slug' => $slug,
        'price' => 0,
        'status' => 'draft',
        'visibility' => 'visible',
        'is_active' => true,
        'is_draft' => true,
        'is_deleted' => false,
        'currency_code' => 'NGN',
    ]);
}

function assignPlan_in_MembershipAutoBumpPass6(User $vendor, MembershipPlan $plan, ?int $intervalHours, float $visibilityMultiplier = 1): UserMembershipPlan
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
            'auto_bump_interval_hours' => $intervalHours,
            'top_credits_per_period' => 0,
            'category_limits' => [],
        ],
        'top_credits_remaining' => 0,
    ]);
}
