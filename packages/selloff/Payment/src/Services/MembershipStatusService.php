<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;

class MembershipStatusService
{
    public function __construct(
        private readonly MembershipEntitlementService $entitlements,
        private readonly MembershipListingGuardService $listingGuard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $subscription = UserMembershipPlan::query()
            ->with('membershipPlan')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $isExpired = true;
        $expiresAt = null;

        if ($subscription) {
            $expiresAt = $subscription->expires_at;
            $isExpired = $expiresAt !== null && $expiresAt->isPast();
        }

        $hasActive = $this->entitlements->hasActiveMembership($user);
        $entitlements = $this->entitlements->effectiveEntitlements($user);
        $listingUsage = $this->entitlements->listingUsage($user, $entitlements);
        $global = $listingUsage['global'] ?? [];

        $canAddProducts = false;
        if (! $this->listingGuard->isEnforced()) {
            $canAddProducts = true;
        } elseif ($hasActive) {
            $canAddProducts = ($global['is_unlimited'] ?? false) || (($global['remaining'] ?? 0) > 0);
        }

        return [
            'has_active_membership' => $hasActive,
            'is_expired' => $subscription !== null && $isExpired,
            'can_add_products' => $canAddProducts,
            'membership_enforced' => $this->listingGuard->isEnforced(),
            'expires_at' => $expiresAt,
            'plan' => $subscription?->membershipPlan ? [
                'id' => $subscription->membershipPlan->id,
                'title' => $subscription->membershipPlan->title,
            ] : null,
            'entitlements' => $entitlements,
            'listing_usage' => $listingUsage,
        ];
    }
}
