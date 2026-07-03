<?php

namespace App\Console\Commands;

use App\LegacyImport\LegacyImportPostProcessor;
use Illuminate\Console\Command;

class LinkOrderTransactionIdsCommand extends Command
{
    protected $signature = 'selloff:link-order-transaction-ids';

    protected $description = 'Set orders.transaction_id from linked payment_transactions when missing';

    public function handle(LegacyImportPostProcessor $processor): int
    {
        $updated = $processor->linkOrderTransactionIds();
        $this->info("Updated {$updated} order row(s).");

        return self::SUCCESS;
    }
}
