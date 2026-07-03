<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class RefundRequestsLegacyImporter extends MultiTableLegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return ['refund_requests', 'refund_requests_messages'];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importRefundRequests($context, $reader);
        $this->importRefundMessages($context, $reader);
    }

    private function importRefundRequests(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('refund_requests') || ! $reader->hasTable('refund_requests')) {
            return;
        }

        foreach ($reader->rows('refund_requests') as $row) {
            $context->notePlanned('refund_requests');

            $legacyId = (int) ($row['id'] ?? 0);
            $orderId = $context->resolveId('orders', (int) ($row['order_id'] ?? 0));
            if ($legacyId <= 0 || $orderId === null) {
                $context->noteSkipped('refund_requests');

                continue;
            }

            $orderItemLegacyId = (int) ($row['order_product_id'] ?? 0);
            $orderItemId = $orderItemLegacyId > 0
                ? $context->resolveId('order_items', $orderItemLegacyId)
                : null;

            $payload = [
                'id' => $legacyId,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'order_number' => isset($row['order_number']) ? (int) $row['order_number'] : null,
                'buyer_id' => $context->resolveId('users', (int) ($row['buyer_id'] ?? 0)),
                'seller_id' => $context->resolveId('users', (int) ($row['seller_id'] ?? 0)),
                'description' => $row['description'] ?? $row['message'] ?? null,
                'status' => $this->mapStatus($row['status'] ?? 0),
                'is_completed' => LegacyValueCoercer::bool($row['is_completed'] ?? 0),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('refund_requests')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'refund_requests', $legacyId, 'refund_requests', $legacyId);
            $context->noteImported('refund_requests');
        }
    }

    private function importRefundMessages(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('refund_requests_messages') || ! $reader->hasTable('refund_requests_messages')) {
            return;
        }

        foreach ($reader->rows('refund_requests_messages') as $row) {
            $context->notePlanned('refund_requests_messages');

            $legacyId = (int) ($row['id'] ?? 0);
            $refundRequestId = $context->resolveId('refund_requests', (int) ($row['request_id'] ?? 0));

            if ($legacyId <= 0 || $refundRequestId === null) {
                $context->noteSkipped('refund_requests_messages');

                continue;
            }

            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'refund_request_id' => $refundRequestId,
                'user_id' => $context->resolveId('users', (int) ($row['user_id'] ?? 0)),
                'message' => $row['message'] ?? null,
                'is_admin' => ! LegacyValueCoercer::bool($row['is_buyer'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                DB::table('refund_messages')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'refund_requests_messages', $legacyId, 'refund_messages', $legacyId);
            $context->noteImported('refund_requests_messages');
        }
    }

    private function mapStatus(mixed $status): string
    {
        if (is_numeric($status)) {
            return match ((int) $status) {
                1 => 'approved',
                2 => 'rejected',
                default => 'pending',
            };
        }

        return (string) $status;
    }
}
