<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Illuminate\Support\Carbon;

class MembershipExpiryService
{
    public const GRACE_DAYS = 3;

    public function deactivateExpiredSubscriptions(?Carbon $asOf = null): int
    {
        $asOf ??= now();
        $cutoff = $asOf->copy()->subDays(self::GRACE_DAYS);

        return UserMembershipPlan::query()
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $cutoff)
            ->update([
                'is_active' => false,
                'updated_at' => $asOf,
            ]);
    }
}
