<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class AbuseReportsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'abuse_reports';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('abuse_reports')) {
            return;
        }

        foreach ($reader->rows('abuse_reports') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            $itemType = LegacyValueCoercer::stringMax($row['item_type'] ?? 'product', 50) ?: 'product';
            $legacyItemId = (int) ($row['item_id'] ?? 0);
            $reporterId = $context->resolveId('users', (int) ($row['report_user_id'] ?? 0));

            if ($legacyId <= 0 || $legacyItemId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $productId = null;
            $userId = null;
            $itemId = null;

            switch ($itemType) {
                case 'product':
                    $productId = $context->resolveId('products', $legacyItemId);
                    $itemId = $productId;
                    break;
                case 'seller':
                    $userId = $context->resolveId('users', $legacyItemId);
                    $itemId = $userId;
                    break;
                case 'review':
                    $itemId = $context->resolveId('reviews', $legacyItemId)
                        ?? $context->resolveId('product_reviews', $legacyItemId);
                    break;
                case 'comment':
                    $itemId = $context->resolveId('comments', $legacyItemId);
                    break;
                case 'feedback':
                    $itemId = $context->resolveId('feedbacks', $legacyItemId);
                    break;
                default:
                    $context->noteSkipped($this->legacyTable());

                    continue 2;
            }

            if ($itemId === null && $productId === null && $userId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'reporter_id' => $reporterId,
                'product_id' => $productId,
                'user_id' => $userId,
                'item_id' => $itemId,
                'report_type' => $itemType,
                'description' => $row['description'] ?? null,
                'status' => 'pending',
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('abuse_reports')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'abuse_reports', $legacyId, 'abuse_reports', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }
}
