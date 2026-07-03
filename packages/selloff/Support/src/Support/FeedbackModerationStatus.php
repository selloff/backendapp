<?php

namespace App\Modules\Selloff\Support\Support;

final class FeedbackModerationStatus
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PENDING, self::APPROVED, self::REJECTED];
    }
}
