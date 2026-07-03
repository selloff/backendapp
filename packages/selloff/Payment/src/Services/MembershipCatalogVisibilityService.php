<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Services\Platform\PlatformSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MembershipCatalogVisibilityService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function isEnforced(): bool
    {
        return filter_var(
            $this->settings->all()['membership_plans_system'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public function applyVendorMembershipVisibility(Builder $query): void
    {
        if (! $this->isEnforced()) {
            return;
        }

        $query->whereExists(function ($sub): void {
            $sub->select(DB::raw(1))
                ->from('user_membership_plans')
                ->whereColumn('user_membership_plans.user_id', 'products.vendor_id')
                ->where('user_membership_plans.is_active', true)
                ->where(function ($expires): void {
                    $expires->whereNull('user_membership_plans.expires_at')
                        ->orWhere('user_membership_plans.expires_at', '>', now());
                });
        });
    }
}
