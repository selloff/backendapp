<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTermDiscount;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MembershipQuoteService
{
    public function __construct(
        private readonly MembershipPlanFeatureResolver $featureResolver,
    ) {}

    public const ALLOWED_MONTHS = [1, 3, 6, 12];

    /**
     * @return Collection<int, MembershipTermDiscount>
     */
    public function activeTermDiscounts(): Collection
    {
        return MembershipTermDiscount::query()
            ->where('is_active', true)
            ->whereIn('months', self::ALLOWED_MONTHS)
            ->orderBy('months')
            ->get();
    }

    /**
     * @return list<array{months: int, discount_percent: string|float, is_active: bool}>
     */
    public function catalogTermDiscounts(): array
    {
        return $this->activeTermDiscounts()
            ->map(fn (MembershipTermDiscount $discount) => [
                'months' => (int) $discount->months,
                'discount_percent' => $discount->discount_percent,
                'is_active' => (bool) $discount->is_active,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(User $user, MembershipPlan $plan, int $months): array
    {
        $this->assertAllowedMonths($months);
        abort_unless($plan->is_active, 422, 'Plan is not active.');

        $monthlyPrice = round((float) $plan->price, 2);
        $discountPercent = $this->discountPercentForMonths($months);
        $subtotal = round($monthlyPrice * $months, 2);
        $discountAmount = round($subtotal * ($discountPercent / 100), 2);
        $grossAmount = round($subtotal - $discountAmount, 2);

        $subscription = $this->activeSubscription($user);
        $purchaseType = $this->resolvePurchaseType($subscription, $plan);
        $creditAmount = $purchaseType === 'upgrade'
            ? $this->calculateUpgradeCredit($subscription)
            : 0.0;
        $amountDue = round(max(0, $grossAmount - $creditAmount), 2);

        return [
            'plan' => $this->formatPlan($plan),
            'months' => $months,
            'monthly_price' => $monthlyPrice,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'gross_amount' => $grossAmount,
            'credit_amount' => $creditAmount,
            'amount_due' => $amountDue,
            'purchase_type' => $purchaseType,
            'currency_code' => $plan->currency_code ?? 'NGN',
            'current_membership' => $this->formatCurrentMembership($subscription),
            'line_items' => $this->lineItems(
                $monthlyPrice,
                $months,
                $discountPercent,
                $discountAmount,
                $creditAmount,
                $amountDue,
                $purchaseType,
            ),
        ];
    }

    public function discountPercentForMonths(int $months): float
    {
        $this->assertAllowedMonths($months);

        $discount = MembershipTermDiscount::query()
            ->where('months', $months)
            ->where('is_active', true)
            ->first();

        return $discount ? (float) $discount->discount_percent : 0.0;
    }

    public function activeSubscription(User $user): ?UserMembershipPlan
    {
        return UserMembershipPlan::query()
            ->with('membershipPlan')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    private function resolvePurchaseType(?UserMembershipPlan $subscription, MembershipPlan $plan): string
    {
        if ($subscription === null || $subscription->membershipPlan === null) {
            return 'new';
        }

        if ((int) $subscription->membership_plan_id === (int) $plan->id) {
            return 'extend';
        }

        $currentOrder = (int) ($subscription->membershipPlan->plan_order ?? 1);
        $selectedOrder = (int) ($plan->plan_order ?? 1);

        if ($selectedOrder > $currentOrder) {
            return 'upgrade';
        }

        throw ValidationException::withMessages([
            'membership_plan_id' => [
                'You can only extend your current plan or upgrade to a higher tier while your subscription is active.',
            ],
        ]);
    }

    private function calculateUpgradeCredit(?UserMembershipPlan $subscription): float
    {
        if ($subscription === null || $subscription->expires_at === null) {
            return 0.0;
        }

        if ($subscription->expires_at->lessThanOrEqualTo(now())) {
            return 0.0;
        }

        $lastPaidAmount = $this->lastPaidAmount($subscription);
        $totalDays = $this->totalPaidDays($subscription);

        if ($lastPaidAmount <= 0 || $totalDays <= 0) {
            return 0.0;
        }

        $remainingDays = max(0, (int) ceil(now()->floatDiffInDays($subscription->expires_at)));

        return round($lastPaidAmount * min($remainingDays, $totalDays) / $totalDays, 2);
    }

    private function lastPaidAmount(UserMembershipPlan $subscription): float
    {
        if ($subscription->last_paid_amount !== null) {
            return round((float) $subscription->last_paid_amount, 2);
        }

        $transaction = MembershipTransaction::query()
            ->where('user_id', $subscription->user_id)
            ->where('membership_plan_id', $subscription->membership_plan_id)
            ->where('status', 'completed')
            ->latest('id')
            ->first();

        if ($transaction === null) {
            return 0.0;
        }

        if ($transaction->amount_charged !== null) {
            return round((float) $transaction->amount_charged, 2);
        }

        return round((float) $transaction->amount, 2);
    }

    private function totalPaidDays(UserMembershipPlan $subscription): int
    {
        if ($subscription->term_months !== null && (int) $subscription->term_months > 0) {
            return (int) $subscription->term_months * 30;
        }

        if ($subscription->starts_at !== null && $subscription->expires_at !== null) {
            return max(1, (int) $subscription->starts_at->diffInDays($subscription->expires_at));
        }

        if ($subscription->membershipPlan !== null) {
            return max(1, (int) ($subscription->membershipPlan->duration_days ?? 30));
        }

        return 30;
    }

    private function assertAllowedMonths(int $months): void
    {
        if (! in_array($months, self::ALLOWED_MONTHS, true)) {
            throw ValidationException::withMessages([
                'months' => ['Subscription length must be 1, 3, 6, or 12 months.'],
            ]);
        }
    }

    /**
     * @return list<array{label: string, amount: float, type: string}>
     */
    private function lineItems(
        float $monthlyPrice,
        int $months,
        float $discountPercent,
        float $discountAmount,
        float $creditAmount,
        float $amountDue,
        string $purchaseType,
    ): array {
        $items = [
            [
                'label' => sprintf('%s × %d month%s', number_format($monthlyPrice, 2), $months, $months === 1 ? '' : 's'),
                'amount' => round($monthlyPrice * $months, 2),
                'type' => 'subtotal',
            ],
        ];

        if ($discountAmount > 0) {
            $items[] = [
                'label' => sprintf('Term discount (%s%%)', rtrim(rtrim(number_format($discountPercent, 2), '0'), '.')),
                'amount' => round(-$discountAmount, 2),
                'type' => 'discount',
            ];
        }

        if ($creditAmount > 0) {
            $items[] = [
                'label' => 'Remaining subscription credit',
                'amount' => round(-$creditAmount, 2),
                'type' => 'credit',
            ];
        }

        $items[] = [
            'label' => match ($purchaseType) {
                'extend' => 'Extension total',
                'upgrade' => 'Upgrade total',
                default => 'Subscription total',
            },
            'amount' => $amountDue,
            'type' => 'total',
        ];

        return $items;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatCurrentMembership(?UserMembershipPlan $subscription): ?array
    {
        if ($subscription === null || $subscription->membershipPlan === null) {
            return null;
        }

        return [
            'plan_id' => $subscription->membership_plan_id,
            'plan_title' => $subscription->membershipPlan->title,
            'plan_order' => (int) ($subscription->membershipPlan->plan_order ?? 1),
            'starts_at' => $subscription->starts_at,
            'expires_at' => $subscription->expires_at,
            'last_paid_amount' => $subscription->last_paid_amount,
            'term_months' => $subscription->term_months,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPlan(MembershipPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'description' => $plan->description,
            'price' => $plan->price,
            'monthly_price' => $plan->price,
            'currency_code' => $plan->currency_code,
            'plan_order' => (int) ($plan->plan_order ?? 1),
            'is_popular' => (bool) ($plan->is_popular ?? false),
            'features' => $this->featureResolver->forPlan($plan),
        ];
    }
}
