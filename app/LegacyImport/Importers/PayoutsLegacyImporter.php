<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class PayoutsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'payouts';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('payouts')) {
            return;
        }

        foreach ($reader->rows('payouts') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            $sellerId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            if ($legacyId <= 0 || $sellerId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'seller_id' => $sellerId,
                'amount' => LegacyValueCoercer::decimal($row['amount'] ?? 0),
                'currency_code' => $row['currency'] ?? null,
                'status' => LegacyValueCoercer::bool($row['status'] ?? 0) ? 'approved' : 'pending',
                'payout_info' => json_encode([
                    'payout_method' => $row['payout_method'] ?? null,
                ]),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('payout_requests')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'payouts', $legacyId, 'payout_requests', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }
}
