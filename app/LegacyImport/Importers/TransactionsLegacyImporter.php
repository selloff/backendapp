<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class TransactionsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'transactions';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('transactions')) {
            return;
        }

        foreach ($reader->rows('transactions') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            $orderId = $context->resolveId('orders', (int) ($row['order_id'] ?? 0));

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'order_id' => $orderId,
                'transaction_number' => $row['payment_id'] ?? $row['transaction_number'] ?? null,
                'payment_method' => $row['payment_method'] ?? null,
                'payment_status' => $row['payment_status'] ?? null,
                'amount' => LegacyValueCoercer::decimal($row['payment_amount'] ?? $row['amount'] ?? 0),
                'currency_code' => $row['currency'] ?? null,
                'exchange_rate' => LegacyValueCoercer::decimal($row['exchange_rate'] ?? 1, 6),
                'metadata' => json_encode([
                    'checkout_token' => $row['checkout_token'] ?? null,
                    'user_type' => $row['user_type'] ?? null,
                    'ip_address' => $row['ip_address'] ?? null,
                ]),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('payment_transactions')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'transactions', $legacyId, 'payment_transactions', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }
}
