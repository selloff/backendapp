<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Support\ProductDraftStatusSync;
use App\Modules\Selloff\Payment\Support\MembershipEntitlementDefaults;
use App\Modules\Selloff\Payment\Support\MembershipListingLimitReason;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Validation\ValidationException;

class MembershipListingGuardService
{
    public function __construct(
        private readonly MembershipEntitlementService $entitlements,
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
     * @param  array<string, mixed>|null  $incomingAttributes
     * @return array{
     *     allowed: bool,
     *     reason: string|null,
     *     message: string|null,
     *     category_id?: int|null,
     *     root_category_id?: int|null
     * }
     */
    public function evaluate(
        User $user,
        ?int $categoryId = null,
        ?Product $existing = null,
        ?array $incomingAttributes = null,
    ): array {
        if (! $this->isEnforced() || $this->bypassesMembershipLimits($user)) {
            return $this->allowed();
        }

        if (! $this->entitlements->hasActiveMembership($user)) {
            return $this->denied(
                MembershipListingLimitReason::MEMBERSHIP_EXPIRED,
                'Your membership plan has expired. Renew your plan to publish listings.',
            );
        }

        if (! $this->willConsumeListingSlot($existing, $incomingAttributes)) {
            return $this->allowed();
        }

        $entitlements = $this->entitlements->effectiveEntitlements($user);
        if ($entitlements === null) {
            return $this->denied(
                MembershipListingLimitReason::MEMBERSHIP_REQUIRED,
                'An active membership plan is required to publish listings.',
            );
        }

        $usage = $this->entitlements->listingUsage($user, $entitlements);
        $global = $usage['global'] ?? [];

        if (! ($global['is_unlimited'] ?? false)) {
            $remaining = $global['remaining'] ?? 0;
            if ($remaining !== null && (int) $remaining <= 0 && $this->needsNewGlobalSlot($existing, $incomingAttributes)) {
                return $this->denied(
                    MembershipListingLimitReason::GLOBAL_LIMIT_REACHED,
                    'You have reached the global listing limit for your membership plan.',
                );
            }
        }

        $resolvedCategoryId = $categoryId
            ?? (int) ($incomingAttributes['category_id'] ?? $existing?->category_id ?? 0)
            ?: null;

        if ($resolvedCategoryId === null) {
            return $this->allowed();
        }

        $rootCategoryId = $this->entitlements->rootCategoryIdFor($resolvedCategoryId);
        if ($rootCategoryId === null) {
            return $this->allowed();
        }

        if (! $this->needsCategorySlot($existing, $incomingAttributes, $resolvedCategoryId)) {
            return $this->allowed();
        }

        $categoryLimit = $this->entitlements->categoryLimitFor($entitlements, $rootCategoryId);
        if ($categoryLimit === null) {
            return $this->allowed();
        }

        if ((int) $categoryLimit === MembershipEntitlementDefaults::UNLIMITED_LISTINGS) {
            return $this->allowed();
        }

        $used = (int) ($usage['by_root_category'][$rootCategoryId] ?? 0);
        if ($used >= (int) $categoryLimit) {
            return $this->denied(
                MembershipListingLimitReason::CATEGORY_LIMIT_REACHED,
                'You have reached the listing limit for this category on your membership plan.',
                $resolvedCategoryId,
                $rootCategoryId,
            );
        }

        return $this->allowed();
    }

    /**
     * @param  array<string, mixed>|null  $incomingAttributes
     */
    public function assertCanConsumeListingSlot(
        User $user,
        ?int $categoryId = null,
        ?Product $existing = null,
        ?array $incomingAttributes = null,
    ): void {
        $result = $this->evaluate($user, $categoryId, $existing, $incomingAttributes);

        if ($result['allowed']) {
            return;
        }

        $field = match ($result['reason']) {
            MembershipListingLimitReason::CATEGORY_LIMIT_REACHED => 'category_id',
            default => 'membership',
        };

        throw ValidationException::withMessages([
            $field => [$result['message'] ?? 'Listing limit reached for your membership plan.'],
        ]);
    }

    private function bypassesMembershipLimits(User $user): bool
    {
        return $user->can('admin_panel');
    }

    /**
     * @param  array<string, mixed>|null  $incomingAttributes
     */
    private function needsNewGlobalSlot(?Product $existing, ?array $incomingAttributes): bool
    {
        return $this->willConsumeListingSlot($existing, $incomingAttributes)
            && ($existing === null || ! $this->entitlements->productCountsAgainstLimits($existing));
    }

    /**
     * @param  array<string, mixed>|null  $incomingAttributes
     */
    private function needsCategorySlot(
        ?Product $existing,
        ?array $incomingAttributes,
        int $resolvedCategoryId,
    ): bool {
        if (! $this->willConsumeListingSlot($existing, $incomingAttributes)) {
            return false;
        }

        if ($existing === null || ! $this->entitlements->productCountsAgainstLimits($existing)) {
            return true;
        }

        $previousCategoryId = (int) ($existing->category_id ?? 0);
        if ($previousCategoryId === $resolvedCategoryId) {
            return false;
        }

        $previousRoot = $this->entitlements->rootCategoryIdFor($previousCategoryId);
        $nextRoot = $this->entitlements->rootCategoryIdFor($resolvedCategoryId);

        return $previousRoot !== $nextRoot;
    }

    /**
     * @param  array<string, mixed>|null  $incomingAttributes
     */
    private function willConsumeListingSlot(?Product $existing, ?array $incomingAttributes): bool
    {
        $state = $this->mergedProductState($existing, $incomingAttributes);

        return $this->entitlements->productCountsAgainstLimits($state);
    }

    /**
     * @param  array<string, mixed>|null  $incomingAttributes
     * @return array<string, mixed>
     */
    private function mergedProductState(?Product $existing, ?array $incomingAttributes): array
    {
        $base = $existing
            ? $existing->only([
                'status',
                'visibility',
                'is_active',
                'is_draft',
                'is_deleted',
                'category_id',
            ])
            : [
                'status' => 'published',
                'visibility' => 'visible',
                'is_active' => true,
                'is_draft' => false,
                'is_deleted' => false,
                'category_id' => null,
            ];

        if ($incomingAttributes !== null) {
            $base = array_merge($base, array_intersect_key($incomingAttributes, $base));
        }

        return ProductDraftStatusSync::apply($base);
    }

    /**
     * @return array{allowed: true, reason: null, message: null}
     */
    private function allowed(): array
    {
        return [
            'allowed' => true,
            'reason' => null,
            'message' => null,
        ];
    }

    /**
     * @return array{
     *     allowed: false,
     *     reason: string,
     *     message: string,
     *     category_id?: int|null,
     *     root_category_id?: int|null
     * }
     */
    private function denied(
        string $reason,
        string $message,
        ?int $categoryId = null,
        ?int $rootCategoryId = null,
    ): array {
        return [
            'allowed' => false,
            'reason' => $reason,
            'message' => $message,
            'category_id' => $categoryId,
            'root_category_id' => $rootCategoryId,
        ];
    }
}
