<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairDigitalSalePricesCommand extends Command
{
    protected $signature = 'selloff:repair-digital-sale-prices';

    protected $description = 'Backfill digital_sales.price and currency_code from matching order line items when missing';

    public function handle(): int
    {
        $updated = DB::update("
            UPDATE digital_sales AS ds
            SET
                price = oi.total_price,
                currency_code = COALESCE(ds.currency_code, o.currency_code)
            FROM order_items AS oi
            INNER JOIN orders AS o ON o.id = ds.order_id
            WHERE ds.order_id = oi.order_id
              AND ds.product_id = oi.product_id
              AND (ds.price IS NULL OR ds.price = 0)
              AND oi.total_price > 0
        ");

        $this->info("Updated {$updated} digital sale row(s).");

        return self::SUCCESS;
    }
}
