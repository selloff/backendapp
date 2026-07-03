<?php

namespace App\LegacyImport\Support;

use Illuminate\Support\Facades\Schema;

final class MembershipLegacyImportMapper
{
    public static function termMonthsFromDays(int $days): int
    {
        if ($days <= 0) {
            return 1;
        }

        return max(1, (int) ceil($days / 30));
    }

    public static function termMonthsFromPlanTitle(?string $title): ?int
    {
        if ($title === null || $title === '') {
            return null;
        }

        if (preg_match('/Number of Days:\s*(\d+)/i', $title, $matches) === 1) {
            return self::termMonthsFromDays((int) $matches[1]);
        }

        if (preg_match('/(\d+)\s*Days/i', $title, $matches) === 1) {
            return self::termMonthsFromDays((int) $matches[1]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function transactionParityColumns(array $row, ?int $planDurationDays = null): array
    {
        $amount = LegacyValueCoercer::decimal($row['payment_amount'] ?? $row['amount'] ?? 0);
        $termMonths = self::termMonthsFromPlanTitle(isset($row['plan_title']) ? (string) $row['plan_title'] : null);

        if ($termMonths === null && $planDurationDays !== null) {
            $termMonths = self::termMonthsFromDays($planDurationDays);
        }

        $termMonths ??= 1;
        $monthlyPrice = $termMonths > 0 ? round($amount / $termMonths, 2) : $amount;

        $columns = [];

        if (Schema::hasColumn('membership_transactions', 'term_months')) {
            $columns['term_months'] = $termMonths;
        }

        if (Schema::hasColumn('membership_transactions', 'purchase_type')) {
            $columns['purchase_type'] = 'new';
        }

        if (Schema::hasColumn('membership_transactions', 'gross_amount')) {
            $columns['gross_amount'] = $amount;
        }

        if (Schema::hasColumn('membership_transactions', 'discount_amount')) {
            $columns['discount_amount'] = 0;
        }

        if (Schema::hasColumn('membership_transactions', 'credit_amount')) {
            $columns['credit_amount'] = 0;
        }

        if (Schema::hasColumn('membership_transactions', 'amount_charged')) {
            $columns['amount_charged'] = $amount;
        }

        if (Schema::hasColumn('membership_transactions', 'monthly_price_at_purchase')) {
            $columns['monthly_price_at_purchase'] = $monthlyPrice;
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function subscriptionParityColumns(array $row): array
    {
        $columns = [];
        $days = (int) ($row['number_of_days'] ?? 0);
        $price = LegacyValueCoercer::decimal($row['price'] ?? 0);
        $isFree = LegacyValueCoercer::bool($row['is_free'] ?? 0);
        $paymentStatus = strtolower((string) ($row['payment_status'] ?? ''));

        if (Schema::hasColumn('user_membership_plans', 'term_months')) {
            $columns['term_months'] = self::termMonthsFromDays($days);
        }

        if (Schema::hasColumn('user_membership_plans', 'last_paid_amount')) {
            $paid = ! $isFree && $price > 0 && (
                $paymentStatus === '' ||
                str_contains($paymentStatus, 'payment') ||
                str_contains($paymentStatus, 'success') ||
                str_contains($paymentStatus, 'received') ||
                str_contains($paymentStatus, 'complete')
            );

            $columns['last_paid_amount'] = $paid ? $price : null;
        }

        return $columns;
    }
}
