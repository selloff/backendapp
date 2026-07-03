<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipPlanCategoryLimit;
use Illuminate\Support\Facades\Schema;

class MembershipPlanCategoryLimitService
{
    /**
     * @param  list<array{category_id: int, max_active_listings: int}>|null  $limits
     */
    public function sync(MembershipPlan $plan, ?array $limits): void
    {
        if (! Schema::hasTable('membership_plan_category_limits')) {
            return;
        }

        if ($limits === null) {
            return;
        }

        MembershipPlanCategoryLimit::query()
            ->where('membership_plan_id', $plan->id)
            ->delete();

        foreach ($limits as $limit) {
            MembershipPlanCategoryLimit::query()->create([
                'membership_plan_id' => $plan->id,
                'category_id' => (int) $limit['category_id'],
                'max_active_listings' => (int) $limit['max_active_listings'],
            ]);
        }
    }
}
