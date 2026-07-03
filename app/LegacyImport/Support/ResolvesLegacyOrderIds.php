<?php

namespace App\LegacyImport\Support;

use App\LegacyImport\MySqlDumpReader;

trait ResolvesLegacyOrderIds
{
    /**
     * @return array<int, int> order_number => order_id
     */
    protected function orderNumberIndex(MySqlDumpReader $reader): array
    {
        if (! $reader->hasTable('orders')) {
            return [];
        }

        $index = [];
        foreach ($reader->rows('orders') as $row) {
            $orderNumber = (int) ($row['order_number'] ?? 0);
            $orderId = (int) ($row['id'] ?? 0);
            if ($orderNumber > 0 && $orderId > 0) {
                $index[$orderNumber] = $orderId;
            }
        }

        return $index;
    }

    /**
     * Legacy wallet_expenses.expense_item_id stores order_number (varchar), not orders.id.
     *
     * @param  array<int, int>  $orderNumberIndex
     */
    protected function resolveOrderIdFromExpenseItem(mixed $expenseItemId, array $orderNumberIndex): ?int
    {
        if ($expenseItemId === null || $expenseItemId === '') {
            return null;
        }

        $string = trim((string) $expenseItemId);
        if ($string === '') {
            return null;
        }

        if (ctype_digit($string)) {
            $asInt = (int) $string;
            if (isset($orderNumberIndex[$asInt])) {
                return $orderNumberIndex[$asInt];
            }

            return $asInt > 0 ? $asInt : null;
        }

        return null;
    }
}
