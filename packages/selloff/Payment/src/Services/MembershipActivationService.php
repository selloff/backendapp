<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Notification\Services\MembershipEmailService;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Illuminate\Support\Carbon;

class MembershipActivationService
{
    public function __construct(
        private readonly MembershipEntitlementService $entitlements,
        private readonly MembershipEmailService $membershipEmails,
    ) {}

    public function activate(
        User $user,
        MembershipPlan $plan,
        string $purchaseType,
        int $months,
        float $amountPaid,
    ): UserMembershipPlan {
        if (in_array($purchaseType, ['new', 'upgrade'], true)) {
            UserMembershipPlan::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $existing = UserMembershipPlan::query()
            ->where('user_id', $user->id)
            ->where('membership_plan_id', $plan->id)
            ->first();

        [$startsAt, $expiresAt] = match ($purchaseType) {
            'extend' => [
                $existing?->starts_at ?? now(),
                $this->extendedExpiry($existing, $months),
            ],
            default => [now(), now()->addMonths($months)],
        };

        $subscription = UserMembershipPlan::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'membership_plan_id' => $plan->id,
            ],
            [
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'is_active' => true,
                'last_paid_amount' => round($amountPaid, 2),
                'term_months' => $months,
                'expiry_notified_at' => null,
            ],
        );

        $plan->loadMissing('categoryLimits');
        $subscription = $subscription->fresh();

        if (
            $purchaseType === 'extend'
            && is_array($subscription->entitlements_snapshot)
            && $subscription->entitlements_snapshot !== []
        ) {
            $subscription->forceFill([
                'top_credits_period_ends_at' => $expiresAt,
            ])->save();

            $subscription = $subscription->fresh(['membershipPlan.categoryLimits']);
            $this->membershipEmails->queueSubscribed($user, $plan, $subscription, $purchaseType, $months, $amountPaid);

            return $subscription;
        }

        $subscription = $this->entitlements->applySnapshot($subscription, $plan);
        $this->membershipEmails->queueSubscribed($user, $plan, $subscription, $purchaseType, $months, $amountPaid);

        return $subscription;
    }

    /**
     * @return array{purchase_type: string, months: int, amount_due: float}
     */
    public function activationPayloadFromTransaction(\App\Modules\Selloff\Payment\Models\MembershipTransaction $transaction): array
    {
        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $quote = is_array($metadata['quote'] ?? null) ? $metadata['quote'] : [];

        return [
            'purchase_type' => (string) ($transaction->purchase_type ?? $quote['purchase_type'] ?? 'new'),
            'months' => (int) ($transaction->term_months ?? $quote['months'] ?? 1),
            'amount_due' => round((float) ($transaction->amount_charged ?? $transaction->amount ?? 0), 2),
        ];
    }

    private function extendedExpiry(?UserMembershipPlan $subscription, int $months): Carbon
    {
        $base = $subscription?->expires_at && $subscription->expires_at->isFuture()
            ? $subscription->expires_at->copy()
            : now();

        return $base->addMonths($months);
    }
}
