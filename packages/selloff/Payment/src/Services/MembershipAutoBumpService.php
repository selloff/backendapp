<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Carbon;

class MembershipAutoBumpService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function isEnabled(): bool
    {
        return filter_var(
            $this->settings->all()['membership_plans_system'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * @return array{vendors_processed: int, products_bumped: int}
     */
    public function run(?Carbon $asOf = null): array
    {
        if (! $this->isEnabled()) {
            return [
                'vendors_processed' => 0,
                'products_bumped' => 0,
            ];
        }

        $asOf ??= now();
        $vendorsProcessed = 0;
        $productsBumped = 0;

        $subscriptions = UserMembershipPlan::query()
            ->where('is_active', true)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $asOf);
            })
            ->get(['id', 'user_id', 'entitlements_snapshot']);

        foreach ($subscriptions as $subscription) {
            $intervalHours = $this->autoBumpIntervalHours($subscription);
            if ($intervalHours === null) {
                continue;
            }

            $vendorsProcessed++;
            $productsBumped += $this->bumpDueListingsForVendor(
                (int) $subscription->user_id,
                $intervalHours,
                $asOf,
            );
        }

        return [
            'vendors_processed' => $vendorsProcessed,
            'products_bumped' => $productsBumped,
        ];
    }

    public function autoBumpIntervalHours(UserMembershipPlan $subscription): ?int
    {
        $snapshot = $subscription->entitlements_snapshot;
        if (! is_array($snapshot)) {
            return null;
        }

        $hours = $snapshot['auto_bump_interval_hours'] ?? null;
        if ($hours === null || (int) $hours <= 0) {
            return null;
        }

        return (int) $hours;
    }

    private function bumpDueListingsForVendor(int $vendorId, int $intervalHours, Carbon $asOf): int
    {
        $cutoff = $asOf->copy()->subHours($intervalHours);

        return Product::query()
            ->where('vendor_id', $vendorId)
            ->where('is_deleted', false)
            ->where(function ($query): void {
                $query->where('is_draft', false)->orWhere('is_draft', 0);
            })
            ->where('status', '!=', 'draft')
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_bumped_at')
                    ->orWhere('last_bumped_at', '<=', $cutoff);
            })
            ->update([
                'last_bumped_at' => $asOf,
                'updated_at' => $asOf,
            ]);
    }
}
