<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\CategoryPathService;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Support\MembershipEntitlementDefaults;
use Illuminate\Support\Facades\Schema;

class MembershipEntitlementService
{
    public function __construct(
        private readonly MembershipPlanEntitlementResolver $entitlementResolver,
        private readonly CategoryPathService $categoryPaths,
    ) {}

    public function hasActiveMembership(User $user): bool
    {
        return $this->activeSubscription($user) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function effectiveEntitlements(User $user): ?array
    {
        $subscription = $this->activeSubscription($user);
        if ($subscription === null) {
            return null;
        }

        $snapshot = $this->snapshotFromSubscription($subscription);
        if ($snapshot !== null) {
            return $snapshot;
        }

        $plan = $subscription->membershipPlan;
        if ($plan === null) {
            return null;
        }

        return $this->entitlementResolver->snapshotFromPlan($plan);
    }

    /**
     * @return array<string, mixed>
     */
    public function vendorEntitlementsPayload(User $user): array
    {
        $subscription = $this->activeSubscription($user);
        $entitlements = $this->effectiveEntitlements($user);
        $usage = $this->listingUsage($user, $entitlements);

        return [
            'has_active_membership' => $subscription !== null,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'starts_at' => $subscription->starts_at,
                'expires_at' => $subscription->expires_at,
                'plan' => $subscription->membershipPlan ? [
                    'id' => $subscription->membershipPlan->id,
                    'title' => $subscription->membershipPlan->title,
                ] : null,
            ] : null,
            'entitlements' => $entitlements,
            'top_credits' => [
                'remaining' => (int) ($subscription?->top_credits_remaining ?? 0),
                'period_started_at' => $subscription?->top_credits_period_started_at,
                'period_ends_at' => $subscription?->top_credits_period_ends_at,
            ],
            'auto_bump' => [
                'enabled' => (int) ($entitlements['auto_bump_interval_hours'] ?? 0) > 0,
                'interval_hours' => $entitlements['auto_bump_interval_hours'] ?? null,
            ],
            'listing_usage' => $usage,
        ];
    }

    public function applySnapshot(UserMembershipPlan $subscription, MembershipPlan $plan): UserMembershipPlan
    {
        $snapshot = $this->entitlementResolver->snapshotFromPlan($plan);
        $topCredits = max(0, (int) ($snapshot['top_credits_per_period'] ?? 0));

        $subscription->forceFill([
            'entitlements_snapshot' => $snapshot,
            'top_credits_remaining' => $topCredits,
            'top_credits_period_started_at' => now(),
            'top_credits_period_ends_at' => $subscription->expires_at,
        ])->save();

        return $subscription->fresh(['membershipPlan.categoryLimits']);
    }

    public function rootCategoryIdFor(?int $categoryId): ?int
    {
        if ($categoryId === null || $categoryId <= 0) {
            return null;
        }

        return $this->categoryPaths->rootCategoryId($categoryId);
    }

    /**
     * @param  array<string, mixed>  $entitlements
     */
    public function categoryLimitFor(array $entitlements, int $rootCategoryId): ?int
    {
        $limits = $entitlements['category_limits'] ?? null;
        if (! is_array($limits)) {
            return null;
        }

        if (array_key_exists($rootCategoryId, $limits)) {
            return (int) $limits[$rootCategoryId];
        }

        if (array_key_exists((string) $rootCategoryId, $limits)) {
            return (int) $limits[(string) $rootCategoryId];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|Product  $product
     */
    public function productCountsAgainstLimits(array|Product $product): bool
    {
        $isDeleted = (bool) ($this->value($product, 'is_deleted') ?? false);
        $isDraft = (bool) ($this->value($product, 'is_draft') ?? false);
        $status = (string) ($this->value($product, 'status') ?? 'published');

        return ! $isDeleted && ! $isDraft && $status !== 'draft';
    }

    /**
     * @return array<string, mixed>
     */
    public function listingUsage(User $user, ?array $entitlements = null): array
    {
        $entitlements ??= $this->effectiveEntitlements($user);
        $counts = $this->activeListingCounts($user->id);

        $globalLimit = $entitlements['global_listing_limit'] ?? null;
        $globalUsed = $counts['global'];
        $globalRemaining = $this->remainingForLimit($globalLimit, $globalUsed);

        $categoryLimits = is_array($entitlements['category_limits'] ?? null) ? $entitlements['category_limits'] : [];
        $byCategory = [];
        $byRootCategory = [];

        foreach ($categoryLimits as $categoryId => $limit) {
            $categoryId = (int) $categoryId;
            $used = (int) ($counts['by_root_category'][$categoryId] ?? 0);
            $remaining = $this->remainingForLimit((int) $limit, $used);
            $byCategory[] = [
                'category_id' => $categoryId,
                'used' => $used,
                'limit' => (int) $limit,
                'remaining' => $remaining,
                'is_unlimited' => (int) $limit === MembershipEntitlementDefaults::UNLIMITED_LISTINGS,
            ];
            $byRootCategory[$categoryId] = $used;
        }

        return [
            'global' => [
                'used' => $globalUsed,
                'limit' => $globalLimit,
                'remaining' => $globalRemaining,
                'is_unlimited' => $globalLimit === null || (int) $globalLimit === MembershipEntitlementDefaults::UNLIMITED_LISTINGS,
            ],
            'by_category' => $byCategory,
            'by_root_category' => $byRootCategory,
        ];
    }

    /**
     * @return array{global: int, by_root_category: array<int, int>}
     */
    private function activeListingCounts(int $userId): array
    {
        $rows = Product::query()
            ->where('vendor_id', $userId)
            ->where('is_deleted', false)
            ->where(function ($query): void {
                $query->where('is_draft', false)->orWhere('is_draft', 0);
            })
            ->get(['id', 'category_id']);

        $byRoot = [];

        foreach ($rows as $product) {
            if (! $this->productCountsAgainstLimits($product)) {
                continue;
            }

            $categoryId = (int) ($product->category_id ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $rootId = $this->categoryPaths->rootCategoryId($categoryId);
            if ($rootId === null) {
                continue;
            }

            $byRoot[$rootId] = ($byRoot[$rootId] ?? 0) + 1;
        }

        return [
            'global' => $rows->filter(fn (Product $product) => $this->productCountsAgainstLimits($product))->count(),
            'by_root_category' => $byRoot,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function snapshotFromSubscription(UserMembershipPlan $subscription): ?array
    {
        if (! Schema::hasColumn('user_membership_plans', 'entitlements_snapshot')) {
            return null;
        }

        $snapshot = $subscription->entitlements_snapshot;
        if (! is_array($snapshot) || $snapshot === []) {
            return null;
        }

        return $snapshot;
    }

    private function activeSubscription(User $user): ?UserMembershipPlan
    {
        return UserMembershipPlan::query()
            ->with(['membershipPlan.categoryLimits'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    private function remainingForLimit(mixed $limit, int $used): ?int
    {
        if ($limit === null) {
            return null;
        }

        $limit = (int) $limit;

        if ($limit === MembershipEntitlementDefaults::UNLIMITED_LISTINGS) {
            return null;
        }

        return max(0, $limit - $used);
    }

    /**
     * @param  array<string, mixed>|Product  $product
     */
    private function value(array|Product $product, string $key): mixed
    {
        if ($product instanceof Product) {
            return $product->getAttribute($key);
        }

        return $product[$key] ?? null;
    }
}
