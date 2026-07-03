<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\LegacyImport\Support\ResolvesLegacyOrderIds;
use Illuminate\Support\Facades\DB;

class VendorEarningsLegacyImporter implements LegacyImporter
{
    use ResolvesLegacyOrderIds;

    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'earnings';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('earnings')) {
            return;
        }

        $orderNumberIndex = $this->orderNumberIndex($reader);

        foreach ($reader->rows('earnings') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $orderId = $context->resolveId('orders', (int) ($row['order_id'] ?? 0));
            if ($orderId === null) {
                $orderNumber = (int) ($row['order_number'] ?? 0);
                $orderId = $orderNumber > 0 ? ($orderNumberIndex[$orderNumber] ?? null) : null;
            }

            if ($orderId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $orderItemLegacyId = (int) ($row['order_product_id'] ?? 0);
            $orderItemId = $orderItemLegacyId > 0
                ? $context->resolveId('order_items', $orderItemLegacyId)
                : null;

            $payload = [
                'id' => $legacyId,
                'seller_id' => $context->resolveId('users', (int) ($row['user_id'] ?? 0)),
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'earned_amount' => LegacyValueCoercer::decimal($row['earned_amount'] ?? 0),
                'sale_amount' => LegacyValueCoercer::decimal($row['sale_amount'] ?? 0),
                'vat_rate' => isset($row['vat_rate']) && $row['vat_rate'] !== ''
                    ? LegacyValueCoercer::decimal($row['vat_rate'], 4)
                    : null,
                'vat_amount' => LegacyValueCoercer::decimal($row['vat_amount'] ?? 0),
                'commission_rate' => isset($row['commission_rate']) && $row['commission_rate'] !== ''
                    ? LegacyValueCoercer::decimal($row['commission_rate'], 4)
                    : null,
                'commission_amount' => LegacyValueCoercer::decimal($row['commission_amount'] ?? $row['commission'] ?? 0),
                'coupon_discount' => LegacyValueCoercer::decimal($row['coupon_discount'] ?? 0),
                'shipping_cost' => LegacyValueCoercer::decimal($row['shipping_cost'] ?? 0),
                'is_refunded' => LegacyValueCoercer::bool($row['is_refunded'] ?? 0),
                'affiliate_data' => $this->affiliateData($row),
                'currency_code' => $row['currency'] ?? $row['currency_code'] ?? null,
                'exchange_rate' => LegacyValueCoercer::decimal($row['exchange_rate'] ?? 1, 6),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('vendor_earnings')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'earnings', $legacyId, 'vendor_earnings', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function affiliateData(array $row): ?string
    {
        $affiliate = [];
        foreach ($row as $key => $value) {
            if (! str_starts_with($key, 'affiliate_') || $value === null || $value === '') {
                continue;
            }
            $affiliate[$key] = $value;
        }

        return $affiliate === [] ? null : json_encode($affiliate);
    }
}
