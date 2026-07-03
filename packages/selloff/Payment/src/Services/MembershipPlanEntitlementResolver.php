<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipPlanCategoryLimit;
use App\Modules\Selloff\Payment\Support\MembershipEntitlementDefaults;
use Illuminate\Support\Facades\Schema;

class MembershipPlanEntitlementResolver
{
    public function __construct(
        private readonly MembershipPlanFeatureResolver $featureResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fromPlan(MembershipPlan $plan): array
    {
        $defaults = MembershipEntitlementDefaults::planDefaults();
        $entitlements = [
            'visibility_multiplier' => $this->decimalValue($plan->visibility_multiplier ?? $defaults['visibility_multiplier'], 1.0),
            'global_listing_limit' => $this->nullableInt($plan->global_listing_limit),
            'auto_bump_interval_hours' => $this->nullableInt($plan->auto_bump_interval_hours),
            'top_credits_per_period' => max(0, (int) ($plan->top_credits_per_period ?? $defaults['top_credits_per_period'])),
            'top_badge_label' => $this->nullableString($plan->top_badge_label),
            'top_rank_weight' => max(0, (int) ($plan->top_rank_weight ?? $defaults['top_rank_weight'])),
            'allow_website_link' => (bool) ($plan->allow_website_link ?? $defaults['allow_website_link']),
            'allow_social_links' => (bool) ($plan->allow_social_links ?? $defaults['allow_social_links']),
            'allow_whatsapp_link' => (bool) ($plan->allow_whatsapp_link ?? $defaults['allow_whatsapp_link']),
            'hide_seller_feedback' => (bool) ($plan->hide_seller_feedback ?? $defaults['hide_seller_feedback']),
            'is_free' => (bool) ($plan->is_free ?? $defaults['is_free']),
            'category_limits' => $this->categoryLimitsForPlan($plan),
            'marketing_benefits' => $this->marketingBenefits($plan),
        ];

        return $entitlements;
    }

    /**
     * @return list<string>
     */
    public function marketingBenefits(MembershipPlan $plan): array
    {
        if (Schema::hasColumn('membership_plans', 'marketing_benefits') && is_array($plan->marketing_benefits)) {
            $benefits = $this->normalizeStringList($plan->marketing_benefits);
            if ($benefits !== []) {
                return $benefits;
            }
        }

        return $this->featureResolver->forPlan($plan);
    }

    /**
     * @return array<int, int>
     */
    public function categoryLimitsForPlan(MembershipPlan $plan): array
    {
        if (! Schema::hasTable('membership_plan_category_limits')) {
            return [];
        }

        if ($plan->relationLoaded('categoryLimits')) {
            $limits = $plan->categoryLimits;
        } else {
            $limits = MembershipPlanCategoryLimit::query()
                ->where('membership_plan_id', $plan->id)
                ->get(['category_id', 'max_active_listings']);
        }

        $mapped = [];

        foreach ($limits as $limit) {
            $mapped[(int) $limit->category_id] = (int) $limit->max_active_listings;
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotFromPlan(MembershipPlan $plan): array
    {
        return [
            'plan_id' => $plan->id,
            'plan_title' => $plan->title,
            'captured_at' => now()->toIso8601String(),
            ...$this->fromPlan($plan),
        ];
    }

    private function decimalValue(mixed $value, float $fallback): float
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        return round((float) $value, 2);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private function normalizeStringList(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }

            $string = trim((string) $value);
            if ($string !== '') {
                $normalized[] = $string;
            }
        }

        return $normalized;
    }
}
