<?php

namespace App\Modules\Selloff\Payment\Support;

final class MembershipListingLimitReason
{
    public const MEMBERSHIP_REQUIRED = 'membership_required';

    public const MEMBERSHIP_EXPIRED = 'membership_expired';

    public const GLOBAL_LIMIT_REACHED = 'global_listing_limit_reached';

    public const CATEGORY_LIMIT_REACHED = 'category_listing_limit_reached';
}
