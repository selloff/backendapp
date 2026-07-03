<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTopApplication;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipTopBoostPass4Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_vendor_can_apply_top_boost_and_credits_decrement(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->topPlan();
        $product = $this->publishedProduct($vendor, $category, 'top-boost-target');

        $this->assignPlan($vendor, $plan, creditsRemaining: 3, rankWeight: 180, badgeLabel: 'Pro TOP+');

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
        $this->assertTrue($product->top_boost_active);
        $this->assertSame(180, (int) $product->top_boost_weight);
        $this->assertNotNull($product->top_boost_expires_at);

        $this->assertDatabaseHas('membership_top_applications', [
            'product_id' => $product->id,
            'credits_consumed' => 1,
        ]);

        $this->assertSame(2, (int) UserMembershipPlan::query()
            ->where('user_id', $vendor->id)
            ->where('is_active', true)
            ->value('top_credits_remaining'));
    }

    public function test_vendor_cannot_apply_top_boost_without_remaining_credits(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->topPlan();
        $product = $this->publishedProduct($vendor, $category, 'top-boost-no-credits');

        $this->assignPlan($vendor, $plan, creditsRemaining: 0);

        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['top_credits']);
    }

    public function test_vendor_cannot_apply_top_boost_to_draft_listing(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->topPlan();

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

        $this->assignPlan($vendor, $plan, creditsRemaining: 2);

        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product']);
    }

    public function test_vendor_cannot_apply_top_boost_when_listing_already_boosted(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->topPlan();
        $product = $this->publishedProduct($vendor, $category, 'top-boost-active');

        $subscription = $this->assignPlan($vendor, $plan, creditsRemaining: 2);

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
    }

    public function test_top_boost_expiry_is_capped_by_membership_period_end(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->topPlan();
        $product = $this->publishedProduct($vendor, $category, 'top-boost-cap');

        $periodEnd = now()->addDays(2);
        $this->assignPlan($vendor, $plan, creditsRemaining: 1, periodEndsAt: $periodEnd);

        Sanctum::actingAs($vendor);

        $response = $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost", [
            'duration_days' => 14,
        ])->assertOk();

        $expiresAt = $response->json('data.product.top_boost_expires_at');
        $this->assertNotNull($expiresAt);
        $this->assertTrue(now()->parse($expiresAt)->lte($periodEnd->copy()->addSecond()));
    }

    public function test_custom_default_top_boost_duration_is_used_when_request_omits_duration(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'membership_top_boost_duration_days' => 10,
        ], 'product');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->topPlan();
        $product = $this->publishedProduct($vendor, $category, 'top-boost-default-duration');

        $this->assignPlan($vendor, $plan, creditsRemaining: 1);

        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/membership/top-credits')
            ->assertOk()
            ->assertJsonPath('data.default_duration_days', 10);

        $response = $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
            ->assertOk();

        $expiresAt = now()->parse((string) $response->json('data.product.top_boost_expires_at'));
        $this->assertTrue($expiresAt->between(now()->addDays(9), now()->addDays(11)));
    }

    private function topPlan(): MembershipPlan
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

    private function publishedProduct(User $vendor, Category $category, string $slug): Product
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

    private function assignPlan(
        User $vendor,
        MembershipPlan $plan,
        int $creditsRemaining,
        int $rankWeight = 180,
        ?string $badgeLabel = 'Pro TOP+',
        ?\Illuminate\Support\Carbon $periodEndsAt = null,
    ): UserMembershipPlan {
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
}
