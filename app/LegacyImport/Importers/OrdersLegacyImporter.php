<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class OrdersLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'orders';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('orders')) {
            return;
        }

        foreach ($reader->rows('orders') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $buyerLegacyId = (int) ($row['buyer_id'] ?? 0);
            $buyerId = $buyerLegacyId > 0 ? $context->resolveId('users', $buyerLegacyId) : null;

            $payload = [
                'id' => $legacyId,
                'buyer_id' => $buyerId,
                'order_number' => (int) ($row['order_number'] ?? $legacyId),
                'price_subtotal' => LegacyValueCoercer::decimal($row['price_subtotal'] ?? 0),
                'price_vat' => LegacyValueCoercer::decimal($row['price_vat'] ?? 0),
                'price_shipping' => LegacyValueCoercer::decimal($row['price_shipping'] ?? 0),
                'price_total' => LegacyValueCoercer::decimal($row['price_total'] ?? 0),
                'currency_code' => $row['price_currency'] ?? $row['currency'] ?? 'NGN',
                'coupon_code' => LegacyValueCoercer::stringMax($row['coupon_code'] ?? null, 255),
                'coupon_discount' => LegacyValueCoercer::decimal($row['coupon_discount'] ?? 0),
                'coupon_discount_rate' => (int) ($row['coupon_discount_rate'] ?? 0),
                'status' => $this->mapStatus($row['status'] ?? 'pending'),
                'payment_method' => $row['payment_method'] ?? 'wallet_balance',
                'payment_status' => $row['payment_status'] ?? 'paid',
                'shipping_snapshot' => $this->shippingSnapshot($row['shipping'] ?? null),
                'global_taxes_data' => LegacyValueCoercer::jsonb($row['global_taxes_data'] ?? null),
                'affiliate_data' => LegacyValueCoercer::jsonb($row['affiliate_data'] ?? null),
                'transaction_fee' => LegacyValueCoercer::decimal($row['transaction_fee'] ?? 0),
                'transaction_fee_rate' => isset($row['transaction_fee_rate']) && $row['transaction_fee_rate'] !== ''
                    ? LegacyValueCoercer::decimal($row['transaction_fee_rate'], 4)
                    : null,
                'checkout_token' => $row['checkout_token'] ?? null,
                'transaction_id' => LegacyValueCoercer::stringMax($row['bank_transaction_number'] ?? null, 255),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()),
            ];

            if (! $context->dryRun) {
                DB::table('orders')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'orders', $legacyId, 'orders', $legacyId);
            $context->noteImported($this->legacyTable());
        }

        $this->importOrderItems($context, $reader);
    }

    private function importOrderItems(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('order_items') || ! ($context->shouldImportTable('order_items') || $context->shouldImportTable('orders'))) {
            return;
        }

        foreach ($reader->rows('order_items') as $row) {
            $context->notePlanned('order_items');

            $legacyId = (int) ($row['id'] ?? 0);
            $orderId = $context->resolveId('orders', (int) ($row['order_id'] ?? 0));
            if ($legacyId <= 0 || $orderId === null) {
                $context->noteSkipped('order_items');

                continue;
            }

            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            $sellerId = $context->resolveId('users', (int) ($row['seller_id'] ?? 0));

            $payload = [
                'id' => $legacyId,
                'order_id' => $orderId,
                'product_id' => $productId,
                'seller_id' => $sellerId,
                'product_type' => $row['product_type'] ?? 'physical',
                'product_title' => $row['product_title'] ?? null,
                'product_sku' => $row['product_sku'] ?? null,
                'quantity' => (int) ($row['product_quantity'] ?? $row['quantity'] ?? 1),
                'unit_price' => LegacyValueCoercer::decimal($row['product_unit_price'] ?? $row['unit_price'] ?? 0),
                'total_price' => LegacyValueCoercer::decimal($row['product_total_price'] ?? $row['total_price'] ?? 0),
                'product_vat' => LegacyValueCoercer::decimal($row['product_vat'] ?? 0),
                'product_vat_rate' => isset($row['product_vat_rate']) && $row['product_vat_rate'] !== ''
                    ? LegacyValueCoercer::decimal($row['product_vat_rate'], 4)
                    : null,
                'seller_shipping_cost' => LegacyValueCoercer::decimal($row['seller_shipping_cost'] ?? 0),
                'order_status' => $row['order_status'] ?? null,
                'is_approved' => LegacyValueCoercer::bool($row['is_approved'] ?? 1),
                'shipping_method' => LegacyValueCoercer::stringMax($row['shipping_method'] ?? null, 255),
                'shipping_tracking_number' => LegacyValueCoercer::stringMax($row['shipping_tracking_number'] ?? null, 255),
                'shipping_tracking_url' => LegacyValueCoercer::stringMax($row['shipping_tracking_url'] ?? null, 500),
                'commission_rate' => isset($row['commission_rate']) && $row['commission_rate'] !== ''
                    ? LegacyValueCoercer::decimal($row['commission_rate'], 4)
                    : null,
                'product_options_snapshot' => LegacyValueCoercer::jsonb($row['product_options_snapshot'] ?? null),
                'product_options_summary' => $row['product_options_summary'] ?? null,
                'product_image_id' => isset($row['image_id']) ? (int) $row['image_id'] : null,
                'product_image_data' => LegacyValueCoercer::jsonb($row['image_data'] ?? null),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()),
            ];

            if (! $context->dryRun) {
                DB::table('order_items')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'order_items', $legacyId, 'order_items', $legacyId);
            $context->noteImported('order_items');
        }
    }

    private function shippingSnapshot(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        $decoded = @unserialize((string) $value, ['allowed_classes' => false]);
        if (is_object($decoded)) {
            return json_encode((array) $decoded);
        }

        if (is_array($decoded)) {
            return json_encode($decoded);
        }

        return LegacyValueCoercer::jsonb($value);
    }

    private function mapStatus(mixed $status): string
    {
        if (is_numeric($status)) {
            return match ((int) $status) {
                0 => 'processing',
                1 => 'completed',
                2 => 'cancelled',
                default => 'processing',
            };
        }

        return (string) $status;
    }
}
