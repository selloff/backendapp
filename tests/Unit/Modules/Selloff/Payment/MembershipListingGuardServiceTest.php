<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipListingGuardService;
use App\Modules\Selloff\Payment\Support\MembershipListingLimitReason;
use App\Services\Platform\PlatformSettingsService;

describe('MembershipListingGuardService', function () {
    beforeEach(function () {
        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    });

    test('allows publish when membership system is disabled', function () {
        app(PlatformSettingsService::class)->upsertMany([
            'membership_plans_system' => false,
        ], 'product');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();

        $result = app(MembershipListingGuardService::class)->evaluate($vendor, $category->id);

        expect($result['allowed'])->toBeTrue();
    });

    test('denies publish when global listing limit is reached', function () {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();

        app(PlatformSettingsService::class)->upsertMany([
            'membership_plans_system' => true,
        ], 'product');

        $plan = MembershipPlan::query()->create([
            'title' => 'Guard Unit Plan',
            'price' => 5000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'is_active' => true,
            'plan_order' => 99,
            'global_listing_limit' => 1,
            'visibility_multiplier' => 1,
            'top_credits_per_period' => 0,
        ]);

        UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);
        UserMembershipPlan::query()->create([
            'user_id' => $vendor->id,
            'membership_plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'entitlements_snapshot' => [
                'plan_id' => $plan->id,
                'plan_title' => $plan->title,
                'global_listing_limit' => 1,
                'visibility_multiplier' => 1,
                'top_credits_per_period' => 0,
                'category_limits' => [],
            ],
            'top_credits_remaining' => 0,
        ]);

        Product::query()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'slug' => 'guard-limit-existing',
            'price' => 1000,
            'status' => 'published',
            'visibility' => 'visible',
            'is_active' => true,
            'is_draft' => false,
            'is_deleted' => false,
            'currency_code' => 'NGN',
        ])->translations()->create(['locale' => 'en', 'title' => 'Existing']);

        $result = app(MembershipListingGuardService::class)->evaluate($vendor, $category->id);

        expect($result['allowed'])->toBeFalse();
        expect($result['reason'])->toBe(MembershipListingLimitReason::GLOBAL_LIMIT_REACHED);
    });

    test('allows draft saves without consuming a listing slot', function () {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();

        app(PlatformSettingsService::class)->upsertMany([
            'membership_plans_system' => true,
        ], 'product');

        $plan = MembershipPlan::query()->create([
            'title' => 'Guard Draft Plan',
            'price' => 5000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'is_active' => true,
            'plan_order' => 100,
            'global_listing_limit' => 1,
            'visibility_multiplier' => 1,
            'top_credits_per_period' => 0,
        ]);

        UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);
        UserMembershipPlan::query()->create([
            'user_id' => $vendor->id,
            'membership_plan_id' => $plan->id,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'entitlements_snapshot' => [
                'plan_id' => $plan->id,
                'plan_title' => $plan->title,
                'global_listing_limit' => 1,
                'visibility_multiplier' => 1,
                'top_credits_per_period' => 0,
                'category_limits' => [],
            ],
            'top_credits_remaining' => 0,
        ]);

        $result = app(MembershipListingGuardService::class)->evaluate(
            $vendor,
            $category->id,
            null,
            ['status' => 'draft', 'is_draft' => true],
        );

        expect($result['allowed'])->toBeTrue();
    });
});
