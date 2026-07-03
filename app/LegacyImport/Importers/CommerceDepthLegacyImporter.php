<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class CommerceDepthLegacyImporter extends MultiTableLegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return ['invoices', 'quote_requests', 'digital_sales'];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importInvoices($context, $reader);
        $this->importQuoteRequests($context, $reader);
        $this->importDigitalSales($context, $reader);
    }

    private function importInvoices(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('invoices') || ! $reader->hasTable('invoices')) {
            return;
        }

        $orderIndex = $this->orderFinancialIndex($reader);

        foreach ($reader->rows('invoices') as $row) {
            $context->notePlanned('invoices');

            $legacyId = (int) ($row['id'] ?? 0);
            $orderLegacyId = (int) ($row['order_id'] ?? 0);
            $orderId = $context->resolveId('orders', $orderLegacyId);
            if ($legacyId <= 0 || $orderId === null) {
                $context->noteSkipped('invoices');

                continue;
            }

            $orderNumber = (int) ($row['order_number'] ?? 0);
            $orderFinancials = $orderIndex[$orderLegacyId] ?? [];
            $buyerLegacyId = $orderFinancials['buyer_legacy_id'] ?? 0;
            $buyerId = $buyerLegacyId > 0 ? $context->resolveId('users', $buyerLegacyId) : null;
            $invoiceNumber = (string) ($orderNumber > 0 ? $orderNumber : ($row['invoice_number'] ?? $legacyId));

            $payload = [
                'id' => $legacyId,
                'order_id' => $orderId,
                'order_number' => $orderNumber > 0 ? $orderNumber : null,
                'buyer_id' => $buyerId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => LegacyValueCoercer::decimal($orderFinancials['price_total'] ?? 0),
                'currency_code' => $orderFinancials['currency_code'] ?? null,
                'client_snapshot' => json_encode($this->clientSnapshot($row)),
                'line_items' => LegacyValueCoercer::jsonb($row['invoice_items'] ?? null),
                'status' => 'paid',
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('invoices')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'invoices', $legacyId, 'invoices', $legacyId);
            $context->noteImported('invoices');
        }
    }

    /**
     * @return array<int, array{buyer_legacy_id: int, price_total: float, currency_code: ?string}>
     */
    private function orderFinancialIndex(MySqlDumpReader $reader): array
    {
        if (! $reader->hasTable('orders')) {
            return [];
        }

        $index = [];
        foreach ($reader->rows('orders') as $row) {
            $orderId = (int) ($row['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $buyerLegacyId = (int) ($row['buyer_id'] ?? 0);
            $index[$orderId] = [
                'buyer_legacy_id' => $buyerLegacyId,
                'price_total' => (float) ($row['price_total'] ?? 0),
                'currency_code' => $row['price_currency'] ?? $row['currency'] ?? null,
            ];
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function clientSnapshot(array $row): array
    {
        $snapshot = [];
        foreach ($row as $key => $value) {
            if (! str_starts_with($key, 'client_') || $value === null || $value === '') {
                continue;
            }
            $snapshot[$key] = $value;
        }

        return $snapshot;
    }

    private function importQuoteRequests(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('quote_requests') || ! $reader->hasTable('quote_requests')) {
            return;
        }

        foreach ($reader->rows('quote_requests') as $row) {
            $context->notePlanned('quote_requests');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('quote_requests');

                continue;
            }

            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            $buyerId = $context->resolveId('users', (int) ($row['buyer_id'] ?? 0));
            $sellerId = $context->resolveId('users', (int) ($row['seller_id'] ?? 0));

            $payload = [
                'id' => $legacyId,
                'product_id' => $productId,
                'buyer_id' => $buyerId,
                'seller_id' => $sellerId,
                'quoted_price' => isset($row['price_offered']) && $row['price_offered'] !== ''
                    ? LegacyValueCoercer::decimal($row['price_offered'])
                    : (isset($row['quoted_price']) ? LegacyValueCoercer::decimal($row['quoted_price']) : null),
                'quantity' => (int) ($row['product_quantity'] ?? $row['quantity'] ?? 1),
                'status' => $row['status'] ?? 'pending',
                'message' => $row['message'] ?? $row['product_options_summary'] ?? null,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('quote_requests')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'quote_requests', $legacyId, 'quote_requests', $legacyId);
            $context->noteImported('quote_requests');
        }
    }

    private function importDigitalSales(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('digital_sales') || ! $reader->hasTable('digital_sales')) {
            return;
        }

        foreach ($reader->rows('digital_sales') as $row) {
            $context->notePlanned('digital_sales');

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped('digital_sales');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'order_id' => $context->resolveId('orders', (int) ($row['order_id'] ?? 0)),
                'product_id' => $context->resolveId('products', (int) ($row['product_id'] ?? 0)),
                'product_title' => LegacyValueCoercer::stringMax($row['product_title'] ?? null, 500),
                'buyer_id' => $context->resolveId('users', (int) ($row['buyer_id'] ?? 0)),
                'seller_id' => $context->resolveId('users', (int) ($row['seller_id'] ?? 0)),
                'license_key' => $row['license_key'] ?? null,
                'purchase_code' => $row['purchase_code'] ?? null,
                'price' => LegacyValueCoercer::decimal($row['price'] ?? 0),
                'currency_code' => LegacyValueCoercer::stringMax($row['currency'] ?? $row['currency_code'] ?? null, 10),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['purchase_date'] ?? $row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['purchase_date'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('digital_sales')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'digital_sales', $legacyId, 'digital_sales', $legacyId);
            $context->noteImported('digital_sales');
        }
    }
}
