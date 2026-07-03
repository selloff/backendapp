<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EscrowTransactionsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'escrow_transactions';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('escrow_transactions')) {
            return;
        }

        foreach ($reader->rows('escrow_transactions') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $itemLegacyId = (int) ($row['item_id'] ?? 0);
            $productId = $itemLegacyId > 0 ? $context->resolveId('products', $itemLegacyId) : null;

            $itemPrice = LegacyValueCoercer::decimal($row['item_price'] ?? 0);
            $commissionAmount = LegacyValueCoercer::decimal($row['commission_amount'] ?? $row['commission'] ?? 0);
            $sellerAmount = LegacyValueCoercer::decimal(
                $row['amount_seller_received'] ?? $row['seller_amount'] ?? max(0, (float) $itemPrice - (float) $commissionAmount),
            );

            $payload = [
                'id' => $legacyId,
                'ref' => LegacyValueCoercer::stringMax($row['ref'] ?? null, 50),
                'buyer_id' => $context->resolveId('users', (int) ($row['buyer_id'] ?? 0)),
                'seller_id' => $context->resolveId('users', (int) ($row['seller_id'] ?? 0)),
                'product_id' => $productId,
                'order_id' => null,
                'amount' => $itemPrice,
                'seller_amount' => $sellerAmount,
                'commission_amount' => $commissionAmount,
                'delivery_cost' => LegacyValueCoercer::decimal($row['delivery_cost'] ?? 0),
                'delivery_address' => $row['delivery_address'] ?? null,
                'currency_code' => $row['currency'] ?? $row['currency_code'] ?? null,
                'status' => $this->mapStatus($row),
                'buyer_agreement_token' => $row['buyer_agreement_token'] ?? null,
                'seller_agreement_token' => $row['seller_agreement_token'] ?? null,
                'buyer_email' => $row['buyer_email'] ?? null,
                'seller_email' => $row['seller_email'] ?? null,
                'buyer_agreed' => LegacyValueCoercer::bool($row['buyer_agreed_to_escrow'] ?? 0),
                'seller_agreed' => LegacyValueCoercer::bool($row['seller_agreed_to_escrow'] ?? 0),
                'payment_link_sent' => LegacyValueCoercer::bool($row['payment_link_sent'] ?? 0),
                'payment_received' => LegacyValueCoercer::bool($row['payment_received'] ?? 0),
                'seller_shipped_item' => LegacyValueCoercer::bool($row['seller_shipped_item'] ?? 0),
                'buyer_confirmed_item_delivery' => LegacyValueCoercer::bool($row['buyer_confirmed_item_delivery'] ?? 0),
                'seller_received_payment' => LegacyValueCoercer::bool($row['seller_received_payment'] ?? 0),
                'transaction_complete' => LegacyValueCoercer::bool($row['transaction_complete'] ?? 0),
                'metadata' => json_encode($this->metadataFromRow($row)),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            $payload = array_merge($payload, $this->paymentPayload($row), $this->timingPayload($row));

            if (! $context->dryRun) {
                DB::table('escrow_transactions')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'escrow_transactions', $legacyId, 'escrow_transactions', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function metadataFromRow(array $row): array
    {
        $metadata = [];
        foreach ($row as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $metadata[$key] = $value;
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapStatus(array $row): string
    {
        if (LegacyValueCoercer::bool($row['transaction_complete'] ?? 0)) {
            return 'completed';
        }

        if (LegacyValueCoercer::bool($row['buyer_agreed_to_escrow'] ?? 0)
            && LegacyValueCoercer::bool($row['seller_agreed_to_escrow'] ?? 0)) {
            return 'processing';
        }

        if (is_numeric($row['status'] ?? null) && (int) $row['status'] > 0) {
            return 'processing';
        }

        return 'pending';
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function paymentPayload(array $row): array
    {
        if (! Schema::hasTable('escrow_transactions')) {
            return [];
        }

        $payload = [];

        if (Schema::hasColumn('escrow_transactions', 'payment_method')) {
            $method = LegacyValueCoercer::stringMax($row['payment_method'] ?? null, 50);
            if ($method !== null) {
                $payload['payment_method'] = $method;
            }
        }

        if (Schema::hasColumn('escrow_transactions', 'payment_reference')) {
            $reference = LegacyValueCoercer::stringMax(
                $row['payment_transaction_ref'] ?? $row['payment_reference'] ?? null,
                120,
            );
            if ($reference !== null) {
                $payload['payment_reference'] = $reference;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function timingPayload(array $row): array
    {
        if (! Schema::hasTable('escrow_transactions')) {
            return [];
        }

        $timestamp = LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? null)
            ?? LegacyValueCoercer::date($row['created_at'] ?? null);

        if ($timestamp === null) {
            return [];
        }

        $payload = [];

        if (Schema::hasColumn('escrow_transactions', 'funded_at')
            && LegacyValueCoercer::bool($row['payment_received'] ?? 0)) {
            $payload['funded_at'] = $timestamp;
        }

        if (Schema::hasColumn('escrow_transactions', 'shipped_at')
            && LegacyValueCoercer::bool($row['seller_shipped_item'] ?? 0)) {
            $payload['shipped_at'] = $timestamp;
        }

        if (Schema::hasColumn('escrow_transactions', 'accepted_at')
            && LegacyValueCoercer::bool($row['buyer_confirmed_item_delivery'] ?? 0)) {
            $payload['accepted_at'] = $timestamp;
        }

        if (Schema::hasColumn('escrow_transactions', 'released_at')
            && (LegacyValueCoercer::bool($row['transaction_complete'] ?? 0)
                || LegacyValueCoercer::bool($row['seller_received_payment'] ?? 0))) {
            $payload['released_at'] = $timestamp;
        }

        if (Schema::hasColumn('escrow_transactions', 'release_scheduled_at')
            && LegacyValueCoercer::bool($row['buyer_confirmed_item_delivery'] ?? 0)
            && ! LegacyValueCoercer::bool($row['transaction_complete'] ?? 0)) {
            $payload['release_scheduled_at'] = $timestamp;
        }

        return $payload;
    }
}
