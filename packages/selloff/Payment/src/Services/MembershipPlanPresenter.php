<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Catalog\Models\Category;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use Illuminate\Support\Facades\Schema;

class MembershipPlanPresenter
{
    public function __construct(
        private readonly MembershipPlanEntitlementResolver $entitlementResolver,
        private readonly MembershipPlanFeatureResolver $featureResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function format(MembershipPlan $plan): array
    {
        $entitlements = $this->entitlementResolver->fromPlan($plan);

        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'description' => $plan->description,
            'price' => $plan->price,
            'monthly_price' => $plan->price,
            'currency_code' => $plan->currency_code,
            'duration_days' => $plan->duration_days,
            'is_active' => (bool) $plan->is_active,
            'plan_order' => Schema::hasColumn('membership_plans', 'plan_order') ? (int) ($plan->plan_order ?? 1) : 1,
            'is_popular' => Schema::hasColumn('membership_plans', 'is_popular') ? (bool) ($plan->is_popular ?? false) : false,
            'features' => $this->featureResolver->forPlan($plan),
            'entitlements' => $entitlements,
            'category_limits' => $this->formatCategoryLimits($entitlements['category_limits'] ?? []),
        ];
    }

    /**
     * @param  array<int, int>  $limits
     * @return list<array<string, int|string|null>>
     */
    private function formatCategoryLimits(array $limits): array
    {
        if ($limits === []) {
            return [];
        }

        $categoryIds = array_map('intval', array_keys($limits));
        $names = Category::query()
            ->with('translations')
            ->whereIn('id', $categoryIds)
            ->get()
            ->mapWithKeys(function (Category $category): array {
                $translation = $category->translations->firstWhere('locale', 'en')
                    ?? $category->translations->first();

                return [
                    $category->id => $translation?->name ?? $category->slug,
                ];
            });

        $formatted = [];

        foreach ($limits as $categoryId => $maxActiveListings) {
            $categoryId = (int) $categoryId;
            $formatted[] = [
                'category_id' => $categoryId,
                'category_name' => $names->get($categoryId),
                'max_active_listings' => (int) $maxActiveListings,
            ];
        }

        usort($formatted, fn (array $left, array $right) => $left['category_id'] <=> $right['category_id']);

        return $formatted;
    }
}
