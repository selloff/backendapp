<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Modules\Selloff\Catalog\Models\Product;

class MembershipTopBoostExpiryService
{
    public function deactivateExpiredBoosts(): int
    {
        return Product::query()
            ->where('top_boost_active', true)
            ->whereNotNull('top_boost_expires_at')
            ->where('top_boost_expires_at', '<=', now())
            ->update([
                'top_boost_active' => false,
                'top_boost_badge_label' => null,
                'updated_at' => now(),
            ]);
    }
}
