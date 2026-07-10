<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Services\Platform\PlatformSettingsService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('homepage latest section ranks membership boosted listing first', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();
    $plan = MembershipPlan::query()->firstOrFail();

    enableMembershipPlatform_in_MembershipHomepageCatalogGap();

    $boosted = publishedProduct_in_MembershipHomepageCatalogGap($vendor, $category, 'homepage-rank-boosted');
    $regular = publishedProduct_in_MembershipHomepageCatalogGap($vendor, $category, 'homepage-rank-regular');

    $timestamp = now()->subHours(2);
    Product::query()->whereIn('id', [$boosted->id, $regular->id])->update([
        'created_at' => $timestamp,
        'is_promoted' => false,
    ]);

    $boosted->forceFill([
        'top_boost_active' => true,
        'top_boost_expires_at' => now()->addDays(5),
        'top_boost_weight' => 300,
        'top_boost_badge_label' => 'Pro TOP+',
    ])->save();

    assignPlan_in_MembershipHomepageCatalogGap($vendor, $plan);

    $sections = collect($this->getJson('/api/v1/homepage')->assertOk()->json('data.sections'));
    $productIds = $sections
        ->flatMap(fn (array $section) => collect($section['products'] ?? [])->pluck('id'))
        ->values();

    expect($productIds->contains($boosted->id))->toBeTrue();
    expect($productIds->contains($regular->id))->toBeTrue();
    expect($productIds->search($boosted->id) < $productIds->search($regular->id))->toBeTrue();
});

test('homepage hides listings from vendors without active membership when enforced', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $category = Category::query()->firstOrFail();

    enableMembershipPlatform_in_MembershipHomepageCatalogGap();

    $visible = publishedProduct_in_MembershipHomepageCatalogGap($vendor, $category, 'homepage-membership-visible');
    $plan = MembershipPlan::query()->firstOrFail();
    assignPlan_in_MembershipHomepageCatalogGap($vendor, $plan);

    $sections = collect($this->getJson('/api/v1/homepage')->assertOk()->json('data.sections'));
    $visibleOnHomepage = $sections
        ->flatMap(fn (array $section) => collect($section['products'] ?? [])->pluck('id'))
        ->contains($visible->id);
    expect($visibleOnHomepage)->toBeTrue();

    UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->update(['is_active' => false]);

    $sectionsAfterExpiry = collect($this->getJson('/api/v1/homepage')->assertOk()->json('data.sections'));
    $hiddenOnHomepage = $sectionsAfterExpiry
        ->flatMap(fn (array $section) => collect($section['products'] ?? [])->pluck('id'))
        ->contains($visible->id);
    expect($hiddenOnHomepage)->toBeFalse();
});

function enableMembershipPlatform_in_MembershipHomepageCatalogGap(): void
{
    app(PlatformSettingsService::class)->upsertMany([
        'membership_plans_system' => true,
        'sort_by_featured_products' => true,
        'index_latest_products' => true,
    ], 'product');
}

function publishedProduct_in_MembershipHomepageCatalogGap(User $vendor, Category $category, string $slug): Product
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

function assignPlan_in_MembershipHomepageCatalogGap(User $vendor, MembershipPlan $plan): void
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
            'global_listing_limit' => 50,
            'visibility_multiplier' => 1,
            'top_credits_per_period' => $plan->top_credits_per_period,
            'top_badge_label' => $plan->top_badge_label,
            'top_rank_weight' => $plan->top_rank_weight,
            'category_limits' => [],
        ],
        'top_credits_remaining' => 0,
    ]);
}
