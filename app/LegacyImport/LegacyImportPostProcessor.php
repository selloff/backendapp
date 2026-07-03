<?php

namespace App\LegacyImport;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyImportPostProcessor
{
    /**
     * Link orders.transaction_id from payment_transactions when missing.
     */
    public function linkOrderTransactionIds(): int
    {
        return DB::update("
            UPDATE orders AS o
            SET transaction_id = pt.transaction_number
            FROM payment_transactions AS pt
            WHERE pt.order_id = o.id
              AND pt.transaction_number IS NOT NULL
              AND pt.transaction_number <> ''
              AND (o.transaction_id IS NULL OR o.transaction_id = '')
        ");
    }

    /**
     * Backfill product recency fields used by membership auto-bump and catalog sort.
     */
    public function backfillProductRecencyFields(): int
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'last_bumped_at')) {
            return 0;
        }

        return DB::update('
            UPDATE products
            SET last_bumped_at = COALESCE(promoted_at, updated_at, created_at)
            WHERE last_bumped_at IS NULL
        ');
    }

    public function run(): void
    {
        $this->linkOrderTransactionIds();
        $this->backfillProductRecencyFields();
    }
}
