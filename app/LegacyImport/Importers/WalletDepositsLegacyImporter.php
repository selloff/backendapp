<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class WalletDepositsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'wallet_deposits';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('wallet_deposits')) {
            return;
        }

        foreach ($reader->rows('wallet_deposits') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            if ($legacyId <= 0 || $userId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'user_id' => $userId,
                'amount' => LegacyValueCoercer::decimal($row['deposit_amount'] ?? $row['amount'] ?? 0),
                'currency_code' => $row['currency'] ?? $row['currency_code'] ?? null,
                'payment_method' => $row['payment_method'] ?? null,
                'status' => LegacyValueCoercer::bool($row['payment_status'] ?? 0) ? 'completed' : 'pending',
                'transaction_id' => $row['payment_id'] ?? $row['transaction_id'] ?? null,
                'checkout_token' => $row['checkout_token'] ?? null,
                'ip_address' => $row['ip_address'] ?? null,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('wallet_deposits')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'wallet_deposits', $legacyId, 'wallet_deposits', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }
}
