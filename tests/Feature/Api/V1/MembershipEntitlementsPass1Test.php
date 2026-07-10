<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can create plan with structured entitlements and category limits', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $rootCategory = Category::query()->whereNull('parent_id')->firstOrFail();
    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/admin/membership-plans', [
        'title' => 'Premium Test Plan',
        'price' => 15000,
        'plan_order' => 3,
        'visibility_multiplier' => 5,
        'global_listing_limit' => 100,
        'auto_bump_interval_hours' => 24,
        'top_credits_per_period' => 5,
        'top_badge_label' => 'Premium TOP+',
        'top_rank_weight' => 250,
        'allow_website_link' => true,
        'allow_social_links' => true,
        'allow_whatsapp_link' => true,
        'hide_seller_feedback' => false,
        'marketing_benefits' => ['Access to Pro Sales'],
        'category_limits' => [
            ['category_id' => $rootCategory->id, 'max_active_listings' => 20],
        ],
    ])->assertCreated();

    $response
        ->assertJsonPath('data.entitlements.visibility_multiplier', 5)
        ->assertJsonPath('data.entitlements.global_listing_limit', 100)
        ->assertJsonPath('data.entitlements.top_credits_per_period', 5)
        ->assertJsonPath('data.entitlements.top_badge_label', 'Premium TOP+')
        ->assertJsonPath('data.category_limits.0.category_id', $rootCategory->id)
        ->assertJsonPath('data.category_limits.0.max_active_listings', 20);

    $this->assertDatabaseHas('membership_plan_category_limits', [
        'category_id' => $rootCategory->id,
        'max_active_listings' => 20,
    ]);
});

test('admin rejects child category in plan limits', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $childCategory = Category::query()->whereNotNull('parent_id')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/membership-plans', [
        'title' => 'Invalid Limits Plan',
        'price' => 1000,
        'category_limits' => [
            ['category_id' => $childCategory->id, 'max_active_listings' => 5],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['category_limits.0.category_id']);
});

test('membership activation stores entitlement snapshot and top credits', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $rootCategory = Category::query()->whereNull('parent_id')->firstOrFail();
    Sanctum::actingAs($admin);

    $create = $this->postJson('/api/v1/admin/membership-plans', [
        'title' => 'VIP Snapshot Plan',
        'price' => 25000,
        'visibility_multiplier' => 10,
        'global_listing_limit' => 50,
        'top_credits_per_period' => 8,
        'top_badge_label' => 'VIP TOP+',
        'category_limits' => [
            ['category_id' => $rootCategory->id, 'max_active_listings' => 15],
        ],
    ])->assertCreated();

    $planId = (int) $create->json('data.id');

    $this->postJson("/api/v1/users/{$vendor->id}/assign-membership-plan", [
        'plan_id' => $planId,
    ])->assertOk();

    $subscription = UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->where('membership_plan_id', $planId)
        ->firstOrFail();

    expect((int) $subscription->top_credits_remaining)->toBe(8);
    expect($subscription->entitlements_snapshot)->toBeArray();
    expect($subscription->entitlements_snapshot['plan_title'])->toBe('VIP Snapshot Plan');
    expect((float) $subscription->entitlements_snapshot['visibility_multiplier'])->toBe(10.0);
    expect((int) $subscription->entitlements_snapshot['category_limits'][(string) $rootCategory->id])->toBe(15);
});

test('vendor entitlements endpoint returns usage and limits', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'entitlements_snapshot' => [
                'plan_id' => $plan->id,
                'plan_title' => $plan->title,
                'visibility_multiplier' => 5,
                'global_listing_limit' => 25,
                'category_limits' => [],
                'top_credits_per_period' => 3,
            ],
            'top_credits_remaining' => 2,
            'top_credits_period_started_at' => now()->subDay(),
            'top_credits_period_ends_at' => now()->addMonth(),
        ],
    );

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/vendor/membership/entitlements')
        ->assertOk()
        ->assertJsonPath('data.has_active_membership', true)
        ->assertJsonPath('data.top_credits.remaining', 2)
        ->assertJsonPath('data.entitlements.global_listing_limit', 25)
        ->assertJsonStructure([
            'data' => [
                'subscription',
                'entitlements',
                'top_credits' => ['remaining', 'period_started_at', 'period_ends_at'],
                'listing_usage' => ['global' => ['used', 'limit', 'remaining'], 'by_category'],
            ],
        ]);
});

test('public membership catalog includes entitlements block', function () {
    $this->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'plans' => [
                    [
                        'id',
                        'title',
                        'entitlements' => [
                            'visibility_multiplier',
                            'global_listing_limit',
                            'top_credits_per_period',
                            'marketing_benefits',
                        ],
                        'category_limits',
                    ],
                ],
            ],
        ]);
});
