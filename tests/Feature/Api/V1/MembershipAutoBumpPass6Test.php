<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipAutoBumpService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipAutoBumpPass6Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_auto_bump_command_refreshes_due_published_listings(): void
    {
        Carbon::setTestNow('2026-06-28 12:00:00');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->autoBumpPlan(intervalHours: 24);

        $due = $this->publishedProduct($vendor, $category, 'auto-bump-due');
        $fresh = $this->publishedProduct($vendor, $category, 'auto-bump-fresh');
        $draft = $this->draftProduct($vendor, $category, 'auto-bump-draft');

        $due->forceFill(['last_bumped_at' => now()->subHours(30)])->save();
        $fresh->forceFill(['last_bumped_at' => now()->subHours(2)])->save();

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, intervalHours: 24);

        $this->artisan('selloff:membership-auto-bump')->assertSuccessful();

        $this->assertTrue($due->fresh()->last_bumped_at?->equalTo(now()));
        $this->assertTrue($fresh->fresh()->last_bumped_at?->equalTo(now()->subHours(2)));
        $this->assertNull($draft->fresh()->last_bumped_at);
    }

    public function test_auto_bump_initializes_last_bumped_at_for_never_bumped_listings(): void
    {
        Carbon::setTestNow('2026-06-28 15:00:00');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->autoBumpPlan(intervalHours: 12);
        $product = $this->publishedProduct($vendor, $category, 'auto-bump-first');

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, intervalHours: 12);

        $this->artisan('selloff:membership-auto-bump')->assertSuccessful();

        $this->assertTrue($product->fresh()->last_bumped_at?->equalTo(now()));
    }

    public function test_auto_bump_skips_vendors_without_interval_entitlement(): void
    {
        Carbon::setTestNow('2026-06-28 16:00:00');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->autoBumpPlan(intervalHours: null);
        $product = $this->publishedProduct($vendor, $category, 'auto-bump-disabled');

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, intervalHours: null);

        $result = app(MembershipAutoBumpService::class)->run();

        $this->assertSame(0, $result['vendors_processed']);
        $this->assertSame(0, $result['products_bumped']);
        $this->assertNull($product->fresh()->last_bumped_at);
    }

    public function test_auto_bump_does_not_run_when_membership_system_is_disabled(): void
    {
        Carbon::setTestNow('2026-06-28 17:00:00');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->autoBumpPlan(intervalHours: 6);
        $product = $this->publishedProduct($vendor, $category, 'auto-bump-system-off');

        $this->assignPlan($vendor, $plan, intervalHours: 6);

        $result = app(MembershipAutoBumpService::class)->run();

        $this->assertSame(0, $result['products_bumped']);
        $this->assertNull($product->fresh()->last_bumped_at);
    }

    public function test_bumped_listing_ranks_above_stale_peer_on_recommended_sort(): void
    {
        Carbon::setTestNow('2026-06-28 18:00:00');

        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $plan = $this->autoBumpPlan(intervalHours: 24);

        $stale = $this->publishedProduct($vendor, $category, 'auto-bump-rank-stale');
        $fresh = $this->publishedProduct($vendor, $category, 'auto-bump-rank-fresh');

        $timestamp = now()->subDays(3);
        Product::query()->whereIn('id', [$stale->id, $fresh->id])->update([
            'created_at' => $timestamp,
            'is_promoted' => false,
        ]);

        $stale->forceFill(['last_bumped_at' => $timestamp])->save();
        $fresh->forceFill(['last_bumped_at' => now()])->save();

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, intervalHours: 24, visibilityMultiplier: 1);

        $response = $this->getJson("/api/v1/products?sort=recommended&category_id={$category->id}&per_page=100")
            ->assertOk();

        $this->assertSame($fresh->id, $response->json('data.data.0.id'));
    }

    public function test_vendor_entitlements_payload_includes_auto_bump_settings(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = $this->autoBumpPlan(intervalHours: 48);

        $this->assignPlan($vendor, $plan, intervalHours: 48, visibilityMultiplier: 2);

        Sanctum::actingAs($vendor);

        $this->getJson('/api/v1/vendor/membership/entitlements')
            ->assertOk()
            ->assertJsonPath('data.auto_bump.enabled', true)
            ->assertJsonPath('data.auto_bump.interval_hours', 48);
    }

    private function enableMembershipSystem(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'membership_plans_system' => true,
        ], 'product');
    }

    private function autoBumpPlan(?int $intervalHours): MembershipPlan
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

    private function publishedProduct(User $vendor, Category $category, string $slug): Product
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

    private function draftProduct(User $vendor, Category $category, string $slug): Product
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

    private function assignPlan(
        User $vendor,
        MembershipPlan $plan,
        ?int $intervalHours,
        float $visibilityMultiplier = 1,
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
                'auto_bump_interval_hours' => $intervalHours,
                'top_credits_per_period' => 0,
                'category_limits' => [],
            ],
            'top_credits_remaining' => 0,
        ]);
    }
}
