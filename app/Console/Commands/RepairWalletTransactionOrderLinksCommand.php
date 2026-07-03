<?php

namespace App\Console\Commands;

use App\LegacyImport\LegacyImportPostProcessor;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\ResolvesLegacyOrderIds;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairWalletTransactionOrderLinksCommand extends Command
{
    use ResolvesLegacyOrderIds;

    protected $signature = 'selloff:repair-wallet-transaction-order-links
                            {--source= : Path to MySQL dump file (defaults to config legacy_import.default_source)}';

    protected $description = 'Backfill wallet_transactions.order_id from legacy wallet_expenses.expense_item_id (order_number)';

    public function handle(): int
    {
        $rawSource = (string) ($this->option('source') ?: config('selloff.legacy_import.default_source'));
        $source = realpath($rawSource) ?: realpath(base_path($rawSource)) ?: '';
        if ($source === '' || ! is_readable($source)) {
            $this->error('Provide --source=PATH to a readable MySQL dump containing wallet_expenses and orders.');

            return self::FAILURE;
        }

        $reader = new MySqlDumpReader($source);
        if (! $reader->hasTable('wallet_expenses')) {
            $this->warn('Dump has no wallet_expenses table.');

            return self::SUCCESS;
        }

        $orderNumberIndex = $this->orderNumberIndex($reader);
        $updated = 0;

        foreach ($reader->rows('wallet_expenses') as $row) {
            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                continue;
            }

            $orderId = $this->resolveOrderIdFromExpenseItem($row['expense_item_id'] ?? null, $orderNumberIndex);
            if ($orderId === null) {
                continue;
            }

            $affected = DB::table('wallet_transactions')
                ->where('legacy_id', $legacyId)
                ->where(function ($query) use ($orderId): void {
                    $query->whereNull('order_id')
                        ->orWhere('order_id', '!=', $orderId);
                })
                ->update(['order_id' => $orderId]);

            $updated += $affected;
        }

        $this->info("Updated {$updated} wallet transaction row(s).");

        return self::SUCCESS;
    }
}
