<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class CouponsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'coupons';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('coupons')) {
            return;
        }

        foreach ($reader->rows('coupons') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $categoryIds = null;
            if (! empty($row['category_ids'])) {
                $ids = array_values(array_filter(array_map('intval', explode(',', (string) $row['category_ids']))));
                $categoryIds = $ids === [] ? null : json_encode($ids);
            }

            $payload = [
                'id' => $legacyId,
                'seller_id' => $context->resolveId('users', (int) ($row['seller_id'] ?? 0)),
                'coupon_code' => (string) ($row['coupon_code'] ?? ('COUPON-'.$legacyId)),
                'discount_rate' => isset($row['discount_rate']) ? (int) $row['discount_rate'] : null,
                'coupon_count' => isset($row['coupon_count']) ? (int) $row['coupon_count'] : null,
                'minimum_order_amount' => LegacyValueCoercer::decimal($row['minimum_order_amount'] ?? 0),
                'currency_code' => $row['currency'] ?? null,
                'usage_type' => $row['usage_type'] ?? 'single',
                'category_ids' => $categoryIds,
                'expires_at' => LegacyValueCoercer::date($row['expiry_date'] ?? $row['expires_at'] ?? null),
                'is_public' => LegacyValueCoercer::bool($row['is_public'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('coupons')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'coupons', $legacyId, 'coupons', $legacyId);
            $context->noteImported($this->legacyTable());
        }

        $this->importCouponProducts($context, $reader);
        $this->importCouponUsages($context, $reader);
    }

    private function importCouponProducts(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('coupon_products')) {
            return;
        }

        foreach ($reader->rows('coupon_products') as $row) {
            $context->notePlanned('coupon_products');

            $couponId = $context->resolveId('coupons', (int) ($row['coupon_id'] ?? 0));
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            if ($couponId === null || $productId === null) {
                $context->noteSkipped('coupon_products');

                continue;
            }

            if (! $context->dryRun) {
                DB::table('coupon_products')->updateOrInsert(
                    ['coupon_id' => $couponId, 'product_id' => $productId],
                    ['coupon_id' => $couponId, 'product_id' => $productId],
                );
            }

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId > 0) {
                $this->maps->remember($context, 'coupon_products', $legacyId, 'coupon_products', $legacyId);
            }

            $context->noteImported('coupon_products');
        }
    }

    private function importCouponUsages(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $reader->hasTable('coupons_used')) {
            return;
        }

        foreach ($reader->rows('coupons_used') as $row) {
            $context->notePlanned('coupons_used');

            $legacyId = (int) ($row['id'] ?? 0);
            $orderId = $context->resolveId('orders', (int) ($row['order_id'] ?? 0));
            $userId = $context->resolveId('users', (int) ($row['user_id'] ?? 0));
            $couponCode = (string) ($row['coupon_code'] ?? '');

            if ($legacyId <= 0 || $couponCode === '') {
                $context->noteSkipped('coupons_used');

                continue;
            }

            $payload = [
                'id' => $legacyId,
                'order_id' => $orderId,
                'user_id' => $userId,
                'coupon_code' => $couponCode,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now(),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()) ?? now(),
            ];

            if (! $context->dryRun) {
                DB::table('coupon_usages')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'coupons_used', $legacyId, 'coupon_usages', $legacyId);
            $context->noteImported('coupons_used');
        }
    }
}
