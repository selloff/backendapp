<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\LegacyImport\Support\ResolvesLegacyOrderIds;
use Illuminate\Support\Facades\DB;

class WalletExpensesLegacyImporter implements LegacyImporter
{
    use ResolvesLegacyOrderIds;

    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'wallet_expenses';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('wallet_expenses')) {
            return;
        }

        $orderNumberIndex = $this->orderNumberIndex($reader);

        foreach ($reader->rows('wallet_expenses') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            if ($legacyId <= 0 || $userId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $orderId = $this->resolveOrderIdFromExpenseItem($row['expense_item_id'] ?? null, $orderNumberIndex);

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'type' => 'expense',
                'amount' => LegacyValueCoercer::decimal($row['expense_amount'] ?? $row['amount'] ?? 0),
                'balance_after' => 0,
                'description' => $row['expense_detail'] ?? $row['expense_item'] ?? $row['description'] ?? null,
                'payment_id' => LegacyValueCoercer::stringMax($row['payment_id'] ?? null, 100),
                'currency_code' => $row['currency'] ?? $row['currency_code'] ?? null,
                'order_id' => $orderId,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('wallet_transactions')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'wallet_expenses', $legacyId, 'wallet_transactions', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }
}
