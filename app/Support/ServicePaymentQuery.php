<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ServicePaymentQuery
{
    /** @var list<string> */
    public const PAID_STATUSES = ['completed', 'payment_received', 'paid', 'success'];

    /** @var list<string> */
    public const PENDING_STATUSES = ['pending', 'pending_payment'];

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function wherePaid(Builder $query): Builder
    {
        $placeholders = implode(', ', array_fill(0, count(self::PAID_STATUSES), '?'));

        return $query->whereRaw('LOWER(status) IN ('.$placeholders.')', self::PAID_STATUSES);
    }

    public static function membershipPaidAmountExpression(): string
    {
        if (Schema::hasColumn('membership_transactions', 'amount_charged')) {
            return 'COALESCE(NULLIF(amount_charged, 0), amount)';
        }

        return 'amount';
    }
}
