<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipPlanCategoryLimit;
use App\Modules\Selloff\Payment\Support\MembershipEntitlementDefaults;
use Illuminate\Support\Facades\Schema;

class MembershipLegacyEntitlementMapper
{
    public function __construct(
        private readonly MembershipPlanFeatureResolver $featureResolver,
        private readonly MembershipPlanEntitlementResolver $entitlementResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function planPayloadFromLegacyRow(array $row): array
    {
        $tier = $this->resolveTier($row);
        $defaults = $this->tierDefaults($tier);
        $marketingBenefits = $this->featureResolver->fromLegacyFeaturesArray($row['features_array'] ?? null);

        $globalLimit = $this->resolveGlobalListingLimit($row, $defaults['global_listing_limit']);

        return [
            'visibility_multiplier' => $defaults['visibility_multiplier'],
            'global_listing_limit' => $globalLimit,
            'auto_bump_interval_hours' => $defaults['auto_bump_interval_hours'],
            'top_credits_per_period' => $defaults['top_credits_per_period'],
            'top_badge_label' => $defaults['top_badge_label'],
            'top_rank_weight' => $defaults['top_rank_weight'],
            'allow_website_link' => $defaults['allow_website_link'],
            'allow_social_links' => $defaults['allow_social_links'],
            'allow_whatsapp_link' => $defaults['allow_whatsapp_link'],
            'hide_seller_feedback' => $defaults['hide_seller_feedback'],
            'is_free' => $defaults['is_free'],
            'marketing_benefits' => $marketingBenefits === [] ? null : json_encode($marketingBenefits),
        ];
    }

    public function syncCategoryLimitsForPlan(MembershipPlan $plan, ?string $tier = null): void
    {
        if (! Schema::hasTable('membership_plan_category_limits')) {
            return;
        }

        $tier ??= $this->resolveTierFromPlan($plan);
        $defaults = $this->tierDefaults($tier);
        $perCategoryCap = $defaults['per_category_cap'];

        $rootCategoryIds = Category::query()
            ->whereNull('parent_id')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rootCategoryIds as $categoryId) {
            MembershipPlanCategoryLimit::query()->updateOrCreate(
                [
                    'membership_plan_id' => $plan->id,
                    'category_id' => $categoryId,
                ],
                [
                    'max_active_listings' => $perCategoryCap,
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotForImportedSubscription(MembershipPlan $plan): array
    {
        return $this->entitlementResolver->snapshotFromPlan($plan->loadMissing('categoryLimits'));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function resolveTier(array $row): string
    {
        if ($this->legacyBool($row['is_free'] ?? 0)) {
            return 'free';
        }

        if ($this->legacyBool($row['is_unlimited_number_of_ads'] ?? 0)) {
            return 'gold';
        }

        $ads = isset($row['number_of_ads']) ? (int) $row['number_of_ads'] : null;

        return match (true) {
            $ads === 5 => 'free',
            $ads !== null && $ads <= 20 => 'bronze',
            $ads !== null && $ads <= 50 => 'silver',
            default => 'gold',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function demoPlanDefinition(string $tier): array
    {
        $defaults = $this->tierDefaults($tier);

        return [
            'title' => $defaults['title'],
            'description' => $defaults['description'],
            'price' => $defaults['price'],
            'currency_code' => 'NGN',
            'duration_days' => $defaults['duration_days'],
            'is_active' => true,
            'plan_order' => $defaults['plan_order'],
            'is_popular' => $defaults['is_popular'],
            'is_free' => $defaults['is_free'],
            'visibility_multiplier' => $defaults['visibility_multiplier'],
            'global_listing_limit' => $defaults['global_listing_limit'],
            'auto_bump_interval_hours' => $defaults['auto_bump_interval_hours'],
            'top_credits_per_period' => $defaults['top_credits_per_period'],
            'top_badge_label' => $defaults['top_badge_label'],
            'top_rank_weight' => $defaults['top_rank_weight'],
            'allow_website_link' => $defaults['allow_website_link'],
            'allow_social_links' => $defaults['allow_social_links'],
            'allow_whatsapp_link' => $defaults['allow_whatsapp_link'],
            'hide_seller_feedback' => $defaults['hide_seller_feedback'],
            'marketing_benefits' => $defaults['marketing_benefits'],
            'features' => $defaults['marketing_benefits'],
        ];
    }

    /**
     * @return list<string>
     */
    public function demoTiers(): array
    {
        return ['free', 'bronze', 'silver', 'gold'];
    }

    private function resolveTierFromPlan(MembershipPlan $plan): string
    {
        if ($plan->is_free) {
            return 'free';
        }

        $globalLimit = $plan->global_listing_limit;

        return match (true) {
            $globalLimit === null || (int) $globalLimit === MembershipEntitlementDefaults::UNLIMITED_LISTINGS => 'gold',
            (int) $globalLimit <= 5 => 'free',
            (int) $globalLimit <= 20 => 'bronze',
            (int) $globalLimit <= 50 => 'silver',
            default => 'gold',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveGlobalListingLimit(array $row, int|null $fallback): ?int
    {
        if ($this->legacyBool($row['is_unlimited_number_of_ads'] ?? 0)) {
            return null;
        }

        if (isset($row['number_of_ads']) && $row['number_of_ads'] !== '' && $row['number_of_ads'] !== null) {
            return max(0, (int) $row['number_of_ads']);
        }

        $fallback = $fallback === MembershipEntitlementDefaults::UNLIMITED_LISTINGS ? null : $fallback;

        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function tierDefaults(string $tier): array
    {
        return match ($tier) {
            'bronze' => [
                'title' => 'Bronze Membership',
                'description' => 'More listings and stronger visibility for growing sellers.',
                'price' => 3500,
                'duration_days' => 30,
                'plan_order' => 2,
                'is_popular' => false,
                'is_free' => false,
                'visibility_multiplier' => 2.0,
                'global_listing_limit' => 20,
                'per_category_cap' => 5,
                'auto_bump_interval_hours' => 72,
                'top_credits_per_period' => 1,
                'top_badge_label' => 'Bronze TOP',
                'top_rank_weight' => 100,
                'allow_website_link' => true,
                'allow_social_links' => false,
                'allow_whatsapp_link' => false,
                'hide_seller_feedback' => false,
                'marketing_benefits' => [
                    'Add up to 20 items for sale',
                    '2x search visibility boost',
                    '1 TOP credit per month',
                    'Website link on product pages',
                ],
            ],
            'silver' => [
                'title' => 'Silver Membership',
                'description' => 'Balanced growth package with category caps and monthly TOP credits.',
                'price' => 9500,
                'duration_days' => 30,
                'plan_order' => 3,
                'is_popular' => true,
                'is_free' => false,
                'visibility_multiplier' => 5.0,
                'global_listing_limit' => 50,
                'per_category_cap' => 10,
                'auto_bump_interval_hours' => 24,
                'top_credits_per_period' => 3,
                'top_badge_label' => 'Silver TOP',
                'top_rank_weight' => 150,
                'allow_website_link' => true,
                'allow_social_links' => true,
                'allow_whatsapp_link' => false,
                'hide_seller_feedback' => false,
                'marketing_benefits' => [
                    'Add up to 50 items for sale',
                    '5x search visibility boost',
                    '3 TOP credits per month',
                    'Website and social links on product pages',
                ],
            ],
            'gold' => [
                'title' => 'Gold Membership',
                'description' => 'Unlimited listings with maximum visibility and premium seller perks.',
                'price' => 19500,
                'duration_days' => 30,
                'plan_order' => 4,
                'is_popular' => false,
                'is_free' => false,
                'visibility_multiplier' => 10.0,
                'global_listing_limit' => null,
                'per_category_cap' => MembershipEntitlementDefaults::UNLIMITED_LISTINGS,
                'auto_bump_interval_hours' => 12,
                'top_credits_per_period' => 8,
                'top_badge_label' => 'Gold TOP',
                'top_rank_weight' => 200,
                'allow_website_link' => true,
                'allow_social_links' => true,
                'allow_whatsapp_link' => true,
                'hide_seller_feedback' => true,
                'marketing_benefits' => [
                    'Unlimited items for sale',
                    '10x search visibility boost',
                    '8 TOP credits per month',
                    'All product-page seller perks enabled',
                ],
            ],
            default => [
                'title' => 'Free Plan',
                'description' => 'Start selling with a small listing allowance.',
                'price' => 0,
                'duration_days' => 0,
                'plan_order' => 1,
                'is_popular' => false,
                'is_free' => true,
                'visibility_multiplier' => 1.0,
                'global_listing_limit' => 5,
                'per_category_cap' => 2,
                'auto_bump_interval_hours' => null,
                'top_credits_per_period' => 0,
                'top_badge_label' => null,
                'top_rank_weight' => 0,
                'allow_website_link' => false,
                'allow_social_links' => false,
                'allow_whatsapp_link' => false,
                'hide_seller_feedback' => false,
                'marketing_benefits' => [
                    'Add up to 5 items for sale',
                    'Free forever',
                ],
            ],
        };
    }

    private function legacyBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
