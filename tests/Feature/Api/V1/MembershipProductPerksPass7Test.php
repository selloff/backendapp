<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\User\Models\VendorProfile;
use App\Services\Platform\PlatformSettingsService;
use Tests\TestCase;

class MembershipProductPerksPass7Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_product_detail_without_membership_system_hides_premium_links(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $product = $this->publishedProduct($vendor, $category, 'perks-system-off');

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.vendor.membership_detail_perks.allow_website_link', false)
            ->assertJsonPath('data.vendor.membership_detail_perks.allow_social_links', false)
            ->assertJsonPath('data.vendor.membership_detail_perks.allow_whatsapp_link', false)
            ->assertJsonPath('data.vendor.membership_detail_perks.hide_seller_feedback', false)
            ->assertJsonPath('data.vendor.hide_seller_feedback', false)
            ->assertJsonPath('data.vendor.social_links', []);
    }

    public function test_product_detail_exposes_filtered_social_links_for_entitled_vendor(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $product = $this->publishedProduct($vendor, $category, 'perks-social-links');
        $plan = $this->detailPerksPlan(
            allowWebsite: true,
            allowSocial: true,
            allowWhatsapp: true,
            hideFeedback: false,
        );

        VendorProfile::query()->where('user_id', $vendor->id)->update([
            'social_media_data' => [
                'facebook' => 'https://facebook.com/selloff-demo',
                'website' => 'demo.selloff.test',
                'whatsapp_url' => 'https://wa.me/2348012345678',
            ],
        ]);

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, [
            'allow_website_link' => true,
            'allow_social_links' => true,
            'allow_whatsapp_link' => true,
            'hide_seller_feedback' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.vendor.membership_detail_perks.allow_website_link', true)
            ->assertJsonPath('data.vendor.membership_detail_perks.allow_social_links', true)
            ->assertJsonPath('data.vendor.membership_detail_perks.allow_whatsapp_link', true)
            ->assertJsonPath('data.vendor.hide_seller_feedback', false);

        $types = collect($response->json('data.vendor.social_links'))->pluck('type')->all();
        $this->assertSame(['website', 'facebook', 'whatsapp'], $types);
        $this->assertSame('https://demo.selloff.test', $response->json('data.vendor.social_links.0.url'));
    }

    public function test_product_detail_hides_social_links_when_perks_are_disabled_on_plan(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $product = $this->publishedProduct($vendor, $category, 'perks-free-plan');
        $plan = $this->detailPerksPlan();

        VendorProfile::query()->where('user_id', $vendor->id)->update([
            'social_media_data' => [
                'facebook' => 'https://facebook.com/selloff-demo',
                'website' => 'https://demo.selloff.test',
            ],
        ]);

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.vendor.social_links', []);
    }

    public function test_hide_seller_feedback_perk_suppresses_public_feedback_endpoints(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $product = $this->publishedProduct($vendor, $category, 'perks-hide-feedback');
        $plan = $this->detailPerksPlan(hideFeedback: true);

        Feedback::query()
            ->where('vendor_id', $vendor->id)
            ->update(['moderation_status' => 'approved']);

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, ['hide_seller_feedback' => true]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.vendor.hide_seller_feedback', true)
            ->assertJsonPath('data.vendor.membership_detail_perks.hide_seller_feedback', true);

        $slug = $vendor->vendorProfile?->slug;
        $this->assertNotEmpty($slug);

        $this->getJson("/api/v1/vendors/{$slug}/feedback")
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.data', []);

        $this->getJson("/api/v1/vendors/{$slug}/feedback/summary")
            ->assertOk()
            ->assertJsonPath('data.total_count', 0)
            ->assertJsonPath('data.percent_positive', 0);
    }

    public function test_product_listing_does_not_include_membership_detail_perks(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $product = $this->publishedProduct($vendor, $category, 'perks-list-hidden');

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $this->detailPerksPlan(allowWebsite: true, allowSocial: true), [
            'allow_website_link' => true,
            'allow_social_links' => true,
        ]);

        $this->getJson('/api/v1/products?search='.urlencode((string) $product->translations()->value('title')))
            ->assertOk()
            ->assertJsonMissingPath('data.data.0.vendor.membership_detail_perks')
            ->assertJsonMissingPath('data.data.0.vendor.social_links')
            ->assertJsonMissingPath('data.data.0.vendor.hide_seller_feedback');
    }

    public function test_whatsapp_link_falls_back_to_vendor_phone_when_url_missing(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $category = Category::query()->firstOrFail();
        $product = $this->publishedProduct($vendor, $category, 'perks-whatsapp-phone');
        $plan = $this->detailPerksPlan(allowWhatsapp: true);

        $vendor->forceFill(['phone_number' => '+234 801 234 5678'])->save();
        VendorProfile::query()->where('user_id', $vendor->id)->update([
            'social_media_data' => [],
        ]);

        $this->enableMembershipSystem();
        $this->assignPlan($vendor, $plan, ['allow_whatsapp_link' => true]);

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.vendor.social_links.0.type', 'whatsapp')
            ->assertJsonPath('data.vendor.social_links.0.url', 'https://wa.me/2348012345678');
    }

    private function detailPerksPlan(
        bool $allowWebsite = false,
        bool $allowSocial = false,
        bool $allowWhatsapp = false,
        bool $hideFeedback = false,
    ): MembershipPlan {
        return MembershipPlan::query()->create([
            'title' => 'Detail Perks Plan',
            'price' => 15000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'is_active' => true,
            'plan_order' => 97,
            'global_listing_limit' => 50,
            'visibility_multiplier' => 2,
            'allow_website_link' => $allowWebsite,
            'allow_social_links' => $allowSocial,
            'allow_whatsapp_link' => $allowWhatsapp,
            'hide_seller_feedback' => $hideFeedback,
        ]);
    }

    private function publishedProduct(User $vendor, Category $category, string $slug): Product
    {
        $product = Product::query()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'slug' => $slug,
            'price' => 3200,
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

    /**
     * @param  array<string, mixed>  $snapshotOverrides
     */
    private function assignPlan(User $vendor, MembershipPlan $plan, array $snapshotOverrides = []): UserMembershipPlan
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
            'entitlements_snapshot' => array_merge([
                'plan_id' => $plan->id,
                'plan_title' => $plan->title,
                'global_listing_limit' => 50,
                'visibility_multiplier' => 2,
                'category_limits' => [],
                'allow_website_link' => (bool) $plan->allow_website_link,
                'allow_social_links' => (bool) $plan->allow_social_links,
                'allow_whatsapp_link' => (bool) $plan->allow_whatsapp_link,
                'hide_seller_feedback' => (bool) $plan->hide_seller_feedback,
            ], $snapshotOverrides),
            'top_credits_remaining' => 0,
            'top_credits_period_started_at' => now()->subDay(),
            'top_credits_period_ends_at' => now()->addMonth(),
        ]);
    }

    private function enableMembershipSystem(): void
    {
        app(PlatformSettingsService::class)->upsertMany([
            'membership_plans_system' => true,
        ], 'product');
    }
}
