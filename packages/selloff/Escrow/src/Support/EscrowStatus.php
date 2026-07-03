<?php

namespace App\Modules\Selloff\Escrow\Support;

final class EscrowStatus
{
    public const PENDING_AGREEMENT = 'pending_agreement';

    public const AWAITING_FUNDING = 'awaiting_funding';

    public const FUNDED = 'funded';

    public const SHIPPED = 'shipped';

    public const AWAITING_ACCEPTANCE = 'awaiting_acceptance';

    public const RELEASING = 'releasing';

    public const COMPLETED = 'completed';

    public const DISPUTED = 'disputed';

    public const CANCELLED = 'cancelled';

    public const REFUNDED = 'refunded';

    public const HELD = 'held';

    /** @var list<string> Legacy statuses still accepted in guards */
    public const LEGACY_PROCESSING = 'processing';

    public const LEGACY_PENDING = 'pending';

    /**
     * @return list<string>
     */
    public static function terminal(): array
    {
        return [self::COMPLETED, self::CANCELLED, self::REFUNDED];
    }

    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::terminal(), true);
    }

    public static function isFundedState(string $status): bool
    {
        return in_array($status, [
            self::FUNDED,
            self::SHIPPED,
            self::AWAITING_ACCEPTANCE,
            self::RELEASING,
            self::LEGACY_PROCESSING,
        ], true);
    }

    public static function normalizeLegacy(string $status): string
    {
        return match ($status) {
            self::LEGACY_PENDING => self::PENDING_AGREEMENT,
            self::LEGACY_PROCESSING => self::AWAITING_FUNDING,
            default => $status,
        };
    }
}
