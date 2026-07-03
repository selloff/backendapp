<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipLegacyEntitlementMapper;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipSubscribePass8Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_public_membership_catalog_includes_structured_entitlements(): void
    {
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
    }

    public function test_demo_seeder_materializes_legacy_four_plan_matrix(): void
    {
        $this->assertDatabaseHas('membership_plans', ['title' => 'Free Plan', 'global_listing_limit' => 5]);
        $this->assertDatabaseHas('membership_plans', ['title' => 'Bronze Membership', 'global_listing_limit' => 20]);
        $this->assertDatabaseHas('membership_plans', ['title' => 'Silver Membership', 'global_listing_limit' => 50]);
        $this->assertDatabaseHas('membership_plans', ['title' => 'Gold Membership']);

        $gold = MembershipPlan::query()->where('title', 'Gold Membership')->firstOrFail();
        $this->assertNull($gold->global_listing_limit);
        $this->assertSame(8, (int) $gold->top_credits_per_period);
        $this->assertTrue((bool) $gold->hide_seller_feedback);
    }

    public function test_authenticated_catalog_includes_current_membership_usage_for_comparison(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = MembershipPlan::query()->where('title', 'Silver Membership')->firstOrFail();

        $this->enableMembershipSystem();
        $this->assignActivePlan($vendor, $plan);

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
    }

    public function test_legacy_mapper_maps_number_of_ads_to_global_listing_limit(): void
    {
        $mapper = app(MembershipLegacyEntitlementMapper::class);

        $bronze = $mapper->planPayloadFromLegacyRow([
            'number_of_ads' => 20,
            'is_free' => 0,
            'is_unlimited_number_of_ads' => 0,
            'features_array' => null,
        ]);

        $this->assertSame(20, $bronze['global_listing_limit']);
        $this->assertSame(2.0, $bronze['visibility_multiplier']);
        $this->assertSame(1, $bronze['top_credits_per_period']);

        $gold = $mapper->planPayloadFromLegacyRow([
            'number_of_ads' => 0,
            'is_free' => 0,
            'is_unlimited_number_of_ads' => 1,
            'features_array' => null,
        ]);

        $this->assertNull($gold['global_listing_limit']);
        $this->assertSame(10.0, $gold['visibility_multiplier']);
        $this->assertTrue((bool) $gold['hide_seller_feedback']);
    }

    public function test_legacy_mapper_resolves_production_tiers(): void
    {
        $mapper = app(MembershipLegacyEntitlementMapper::class);

        $this->assertSame('free', $mapper->resolveTier(['is_free' => 1, 'number_of_ads' => 5]));
        $this->assertSame('bronze', $mapper->resolveTier(['number_of_ads' => 20]));
        $this->assertSame('silver', $mapper->resolveTier(['number_of_ads' => 50]));
        $this->assertSame('gold', $mapper->resolveTier(['is_unlimited_number_of_ads' => 1]));
    }

    public function test_legacy_import_maps_number_of_ads_columns_onto_entitlement_fields(): void
    {
        $this->artisan('selloff:import-legacy-data', [
            '--source' => base_path('tests/fixtures/legacy-membership-entitlements.sql'),
            '--table' => 'membership_plans',
            '--skip-verify' => true,
        ])->assertSuccessful();

        $bronze = MembershipPlan::query()->findOrFail(9901);
        $this->assertSame(20, (int) $bronze->global_listing_limit);
        $this->assertSame(2.0, (float) $bronze->visibility_multiplier);

        $gold = MembershipPlan::query()->findOrFail(9902);
        $this->assertNull($gold->global_listing_limit);
        $this->assertSame(10.0, (float) $gold->visibility_multiplier);
        $this->assertTrue((bool) $gold->hide_seller_feedback);
    }

    private function enableMembershipSystem(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'membership_plans_system' => true,
        ], 'product');
    }

    private function assignActivePlan(User $vendor, MembershipPlan $plan): void
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
}
