<?php

namespace App\Modules\Selloff\Payment\Support;

class MembershipEntitlementDefaults
{
    public const UNLIMITED_LISTINGS = -1;

    /**
     * @return array<string, mixed>
     */
    public static function planDefaults(): array
    {
        return [
            'visibility_multiplier' => 1.0,
            'global_listing_limit' => null,
            'auto_bump_interval_hours' => null,
            'top_credits_per_period' => 0,
            'top_badge_label' => null,
            'top_rank_weight' => 0,
            'allow_website_link' => false,
            'allow_social_links' => false,
            'allow_whatsapp_link' => false,
            'hide_seller_feedback' => false,
            'is_free' => false,
        ];
    }
}
