<?php

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipLegacyEntitlementMapper;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('public membership catalog includes structured entitlements', function () {
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
                            'auto_bump_interval_hours',
                            'marketing_benefits',
                        ],
                        'category_limits' => [
                            [
                                'category_id',
                                'category_name',
                                'max_active_listings',
                            ],
                        ],
                    ],
                ],
                'term_discounts',
            ],
        ]);
});

test('demo seeder materializes legacy four plan matrix', function () {
    $this->assertDatabaseHas('membership_plans', ['title' => 'Free Plan', 'global_listing_limit' => 5]);
    $this->assertDatabaseHas('membership_plans', ['title' => 'Bronze Membership', 'global_listing_limit' => 20]);
    $this->assertDatabaseHas('membership_plans', ['title' => 'Silver Membership', 'global_listing_limit' => 50]);
    $this->assertDatabaseHas('membership_plans', ['title' => 'Gold Membership']);

    $gold = MembershipPlan::query()->where('title', 'Gold Membership')->firstOrFail();
    expect($gold->global_listing_limit)->toBeNull();
    expect((int) $gold->top_credits_per_period)->toBe(8);
    expect((bool) $gold->hide_seller_feedback)->toBeTrue();
});

test('authenticated catalog includes current membership usage for comparison', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Silver Membership')->firstOrFail();

    enableMembershipSystem_in_MembershipSubscribePass8();
    assignActivePlan_in_MembershipSubscribePass8($vendor, $plan);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonPath('data.current_membership.has_active_membership', true)
        ->assertJsonPath('data.current_membership.plan.title', 'Silver Membership')
        ->assertJsonStructure([
            'data' => [
                'current_membership' => [
                    'entitlements',
                    'listing_usage' => [
                        'global' => ['used', 'limit', 'remaining'],
                        'by_category',
                    ],
                ],
            ],
        ]);
});

test('legacy mapper maps number of ads to global listing limit', function () {
    $mapper = app(MembershipLegacyEntitlementMapper::class);

    $bronze = $mapper->planPayloadFromLegacyRow([
        'number_of_ads' => 20,
        'is_free' => 0,
        'is_unlimited_number_of_ads' => 0,
        'features_array' => null,
    ]);

    expect($bronze['global_listing_limit'])->toBe(20);
    expect($bronze['visibility_multiplier'])->toBe(2.0);
    expect($bronze['top_credits_per_period'])->toBe(1);

    $gold = $mapper->planPayloadFromLegacyRow([
        'number_of_ads' => 0,
        'is_free' => 0,
        'is_unlimited_number_of_ads' => 1,
        'features_array' => null,
    ]);

    expect($gold['global_listing_limit'])->toBeNull();
    expect($gold['visibility_multiplier'])->toBe(10.0);
    expect((bool) $gold['hide_seller_feedback'])->toBeTrue();
});

test('legacy mapper resolves production tiers', function () {
    $mapper = app(MembershipLegacyEntitlementMapper::class);

    expect($mapper->resolveTier(['is_free' => 1, 'number_of_ads' => 5]))->toBe('free');
    expect($mapper->resolveTier(['number_of_ads' => 20]))->toBe('bronze');
    expect($mapper->resolveTier(['number_of_ads' => 50]))->toBe('silver');
    expect($mapper->resolveTier(['is_unlimited_number_of_ads' => 1]))->toBe('gold');
});

test('legacy import maps number of ads columns onto entitlement fields', function () {
    $this->artisan('selloff:import-legacy-data', [
        '--source' => base_path('tests/fixtures/legacy-membership-entitlements.sql'),
        '--table' => 'membership_plans',
        '--skip-verify' => true,
    ])->assertSuccessful();

    $bronze = MembershipPlan::query()->findOrFail(9901);
    expect((int) $bronze->global_listing_limit)->toBe(20);
    expect((float) $bronze->visibility_multiplier)->toBe(2.0);

    $gold = MembershipPlan::query()->findOrFail(9902);
    expect($gold->global_listing_limit)->toBeNull();
    expect((float) $gold->visibility_multiplier)->toBe(10.0);
    expect((bool) $gold->hide_seller_feedback)->toBeTrue();
});

function enableMembershipSystem_in_MembershipSubscribePass8(): void
{
    app(PlatformSettingsService::class)->upsertMany([
        'membership_plans_system' => true,
    ], 'product');
}

function assignActivePlan_in_MembershipSubscribePass8(User $vendor, MembershipPlan $plan): void
{
    $mapper = app(MembershipLegacyEntitlementMapper::class);
    $snapshot = $mapper->snapshotForImportedSubscription($plan);

    \App\Modules\Selloff\Payment\Models\UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->update(['is_active' => false]);

    \App\Modules\Selloff\Payment\Models\UserMembershipPlan::query()->create([
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addMonth(),
        'is_active' => true,
        'entitlements_snapshot' => $snapshot,
        'top_credits_remaining' => (int) ($snapshot['top_credits_per_period'] ?? 0),
        'top_credits_period_started_at' => now()->subDay(),
        'top_credits_period_ends_at' => now()->addMonth(),
    ]);
}
