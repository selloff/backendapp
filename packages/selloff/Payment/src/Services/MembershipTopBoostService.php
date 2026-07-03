<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\MembershipTopApplication;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipTopBoostService
{
    public function __construct(
        private readonly MembershipEntitlementService $entitlements,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function defaultDurationDays(): int
    {
        $value = $this->settings->all()['membership_top_boost_duration_days'] ?? 7;

        return max(1, min(90, (int) $value));
    }

    /**
     * @return array<string, mixed>
     */
    public function topCreditsPayload(User $user): array
    {
        $subscription = $this->activeSubscription($user);
        $entitlements = $this->entitlements->effectiveEntitlements($user);

        $activeApplications = MembershipTopApplication::query()
            ->whereHas('userMembershipPlan', fn ($query) => $query->where('user_id', $user->id))
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['product' => fn ($query) => $query->select(['id', 'slug'])])
            ->latest('applied_at')
            ->limit(20)
            ->get()
            ->map(fn (MembershipTopApplication $application) => [
                'id' => $application->id,
                'product_id' => $application->product_id,
                'product_slug' => $application->product?->slug,
                'credits_consumed' => (int) $application->credits_consumed,
                'applied_at' => $application->applied_at,
                'expires_at' => $application->expires_at,
            ])
            ->values()
            ->all();

        return [
            'has_active_membership' => $subscription !== null,
            'remaining' => (int) ($subscription?->top_credits_remaining ?? 0),
            'per_period_allowance' => max(0, (int) ($entitlements['top_credits_per_period'] ?? 0)),
            'badge_label' => $entitlements['top_badge_label'] ?? null,
            'rank_weight' => max(0, (int) ($entitlements['top_rank_weight'] ?? 0)),
            'period_started_at' => $subscription?->top_credits_period_started_at,
            'period_ends_at' => $subscription?->top_credits_period_ends_at,
            'default_duration_days' => $this->defaultDurationDays(),
            'active_applications' => $activeApplications,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(User $user, Product $product, ?int $durationDays = null): array
    {
        abort_unless(
            (int) $product->vendor_id === (int) $user->id || $user->can('admin_panel'),
            403,
            'Product does not belong to this vendor.',
        );

        if (! $this->entitlements->productCountsAgainstLimits($product)) {
            throw ValidationException::withMessages([
                'product' => ['Only published listings can receive a membership TOP boost.'],
            ]);
        }

        if ($this->hasActiveTopBoost($product)) {
            throw ValidationException::withMessages([
                'top_boost' => ['This listing already has an active membership TOP boost.'],
            ]);
        }

        return DB::transaction(function () use ($user, $product, $durationDays): array {
            $subscription = UserMembershipPlan::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($subscription === null) {
                throw ValidationException::withMessages([
                    'membership' => ['An active membership plan is required to apply TOP credits.'],
                ]);
            }

            $entitlements = $this->entitlements->effectiveEntitlements($user);
            if ($entitlements === null) {
                throw ValidationException::withMessages([
                    'membership' => ['Membership entitlements are unavailable for your current plan.'],
                ]);
            }

            $remaining = (int) $subscription->top_credits_remaining;
            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'top_credits' => ['You have no TOP credits remaining on your membership plan.'],
                ]);
            }

            $duration = $durationDays ?? $this->defaultDurationDays();
            $duration = max(1, min(90, $duration));

            $expiresAt = $this->resolveBoostExpiry($subscription, $duration);
            $rankWeight = max(0, (int) ($entitlements['top_rank_weight'] ?? 0));
            $badgeLabel = $entitlements['top_badge_label'] ?? null;
            $appliedAt = now();

            $application = MembershipTopApplication::query()->create([
                'user_membership_plan_id' => $subscription->id,
                'product_id' => $product->id,
                'credits_consumed' => 1,
                'applied_at' => $appliedAt,
                'expires_at' => $expiresAt,
            ]);

            $subscription->forceFill([
                'top_credits_remaining' => $remaining - 1,
            ])->save();

            $product->forceFill([
                'top_boost_active' => true,
                'top_boost_expires_at' => $expiresAt,
                'top_boost_weight' => $rankWeight,
                'top_boost_badge_label' => $badgeLabel,
            ])->save();

            return [
                'application' => [
                    'id' => $application->id,
                    'product_id' => $application->product_id,
                    'credits_consumed' => (int) $application->credits_consumed,
                    'applied_at' => $application->applied_at,
                    'expires_at' => $application->expires_at,
                ],
                'product' => [
                    'id' => $product->id,
                    'top_boost_active' => true,
                    'top_boost_expires_at' => $product->top_boost_expires_at,
                    'top_boost_weight' => (int) $product->top_boost_weight,
                ],
                'top_credits' => [
                    'remaining' => (int) $subscription->fresh()->top_credits_remaining,
                    'period_started_at' => $subscription->top_credits_period_started_at,
                    'period_ends_at' => $subscription->top_credits_period_ends_at,
                ],
                'badge_label' => $badgeLabel,
                'rank_weight' => $rankWeight,
            ];
        });
    }

    private function activeSubscription(User $user): ?UserMembershipPlan
    {
        return UserMembershipPlan::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    private function hasActiveTopBoost(Product $product): bool
    {
        if (! (bool) $product->top_boost_active) {
            return false;
        }

        $expiresAt = $product->top_boost_expires_at;

        return $expiresAt === null || $expiresAt->isFuture();
    }

    private function resolveBoostExpiry(UserMembershipPlan $subscription, int $durationDays): Carbon
    {
        $expiresAt = now()->addDays($durationDays);

        if ($subscription->top_credits_period_ends_at !== null && $subscription->top_credits_period_ends_at->lt($expiresAt)) {
            $expiresAt = $subscription->top_credits_period_ends_at->copy();
        }

        if ($subscription->expires_at !== null && $subscription->expires_at->lt($expiresAt)) {
            $expiresAt = $subscription->expires_at->copy();
        }

        return $expiresAt;
    }
}
