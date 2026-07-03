<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipListingRankPass5Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_recommended_sort_ranks_higher_visibility_multiplier_above_peer_listing(): void
    {
        $highVendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $lowVendor = User::query()->where('email', 'vendor2@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->rankPlan();

        $highProduct = $this->publishedProduct($highVendor, $category, 'rank-high-visibility');
        $lowProduct = $this->publishedProduct($lowVendor, $category, 'rank-low-visibility');

        $timestamp = now()->subHour();
        Product::query()->whereIn('id', [$highProduct->id, $lowProduct->id])->update(['created_at' => $timestamp]);

        $this->assignPlan($highVendor, $plan, visibilityMultiplier: 10);
        $this->assignPlan($lowVendor, $plan, visibilityMultiplier: 1);

        $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');

        $this->assertTrue($ids->search($highProduct->id) < $ids->search($lowProduct->id));
    }

    public function test_recommended_sort_prioritizes_active_top_boost_weight_over_non_boosted_peer(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->rankPlan();

        $boosted = $this->publishedProduct($vendor, $category, 'rank-top-boosted');
        $regular = $this->publishedProduct($vendor, $category, 'rank-regular-listing');

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

        $this->assignPlan($vendor, $plan, visibilityMultiplier: 1);

        $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');

        $this->assertSame($boosted->id, $ids->first());
    }

    public function test_expired_top_boost_does_not_outrank_active_boosted_peer(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->rankPlan();

        $expiredBoost = $this->publishedProduct($vendor, $category, 'rank-expired-top');
        $activeBoost = $this->publishedProduct($vendor, $category, 'rank-active-top');

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

        $this->assignPlan($vendor, $plan, visibilityMultiplier: 1);

        $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');

        $this->assertTrue($ids->search($activeBoost->id) < $ids->search($expiredBoost->id));
    }

    public function test_promoted_vip_listing_still_outranks_membership_top_boost_on_recommended_sort(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->rankPlan();

        $vip = $this->publishedProduct($vendor, $category, 'rank-vip-listing');
        $top = $this->publishedProduct($vendor, $category, 'rank-top-only');

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

        $this->assignPlan($vendor, $plan, visibilityMultiplier: 1);

        $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
            ->assertOk();

        $this->assertSame($vip->id, $response->json('data.data.0.id'));
    }

    public function test_price_sort_ignores_membership_ranking(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->rankPlan();

        $expensiveBoosted = $this->publishedProduct($vendor, $category, 'rank-expensive-boosted');
        $cheapRegular = $this->publishedProduct($vendor, $category, 'rank-cheap-regular');

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

        $this->assignPlan($vendor, $plan, visibilityMultiplier: 10);

        $response = $this->getJson("/api/v1/products?sort=price&direction=asc&category_id={$category->id}&per_page=100")
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');

        $this->assertTrue($ids->search($cheapRegular->id) < $ids->search($expensiveBoosted->id));
    }

    public function test_product_resource_exposes_top_boost_badge_fields(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->rankPlan(credits: 2);
        $product = $this->publishedProduct($vendor, $category, 'rank-resource-badge');

        $this->assignPlan($vendor, $plan, visibilityMultiplier: 2, creditsRemaining: 2);

        Sanctum::actingAs($vendor);

        $this->postJson("/api/v1/vendor/products/{$product->id}/apply-top-boost")
            ->assertOk();

        $this->getJson('/api/v1/products?search=Rank+resource+badge')
            ->assertOk()
            ->assertJsonPath('data.data.0.is_top_boosted', true)
            ->assertJsonPath('data.data.0.top_badge_label', 'Pro TOP+');
    }

    public function test_expire_membership_top_boosts_command_clears_active_flag(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $product = $this->publishedProduct($vendor, $category, 'rank-expire-command');

        $product->forceFill([
            'top_boost_active' => true,
            'top_boost_expires_at' => now()->subHour(),
            'top_boost_weight' => 90,
            'top_boost_badge_label' => 'Pro TOP+',
        ])->save();

        $this->artisan('selloff:expire-membership-top-boosts')->assertSuccessful();

        $product->refresh();
        $this->assertFalse($product->top_boost_active);
        $this->assertNull($product->top_boost_badge_label);
    }

    private function rankPlan(int $credits = 0): MembershipPlan
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

    private function publishedProduct(User $vendor, Category $category, string $slug): Product
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

    private function assignPlan(
        User $vendor,
        MembershipPlan $plan,
        float $visibilityMultiplier,
        int $creditsRemaining = 0,
    ): UserMembershipPlan {
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
}
