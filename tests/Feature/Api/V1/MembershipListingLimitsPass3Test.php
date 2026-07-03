<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipListingLimitsPass3Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_cannot_publish_when_global_listing_limit_is_reached(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->limitedPlan(globalLimit: 1, categoryId: $category->id, categoryLimit: 5);

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, globalLimit: 1, categoryId: $category->id, categoryLimit: 5);

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
    }

    public function test_vendor_can_save_draft_without_consuming_listing_limit(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->limitedPlan(globalLimit: 1, categoryId: $category->id, categoryLimit: 1);

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, globalLimit: 1, categoryId: $category->id, categoryLimit: 1);

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
    }

    public function test_vendor_status_includes_listing_usage_and_can_add_products_flag(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->limitedPlan(globalLimit: 2, categoryId: $category->id, categoryLimit: 1);

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, globalLimit: 2, categoryId: $category->id, categoryLimit: 1);

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
    }

    public function test_catalog_hides_products_from_vendors_without_active_membership_when_enforced(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->limitedPlan(globalLimit: 5, categoryId: $category->id, categoryLimit: 5);

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, globalLimit: 5, categoryId: $category->id, categoryLimit: 5);

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
    }

    public function test_deactivate_expired_memberships_command_marks_subscriptions_inactive_after_grace(): void
    {
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

        $this->assertFalse((bool) $subscription->fresh()->is_active);
    }

    private function enableMembershipSystem(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'membership_plans_system' => true,
        ], 'product');
    }

    private function limitedPlan(int $globalLimit, int $categoryId, int $categoryLimit): MembershipPlan
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

    private function assignPlan(
        User $vendor,
        MembershipPlan $plan,
        int $globalLimit,
        int $categoryId,
        int $categoryLimit,
    ): void {
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
}
