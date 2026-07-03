<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use Illuminate\Support\Facades\DB;

class AffiliateLegacyImporter extends MultiTableLegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    /**
     * @return list<string>
     */
    public function legacyTables(): array
    {
        return ['affiliate_links', 'affiliate_earnings'];
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        $this->importLinks($context, $reader);
        $this->importEarnings($context, $reader);
    }

    private function importLinks(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('affiliate_links') || ! $reader->hasTable('affiliate_links')) {
            return;
        }

        foreach ($reader->rows('affiliate_links') as $row) {
            $context->notePlanned('affiliate_links');

            $legacyId = (int) ($row['id'] ?? 0);
            $referrerId = $context->resolveId('users', (int) ($row['referrer_id'] ?? 0));
            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));

            if ($legacyId <= 0 || $referrerId === null || $productId === null) {
                $context->noteSkipped('affiliate_links');

                continue;
            }

            $sellerId = $context->resolveId('users', (int) ($row['seller_id'] ?? 0));
            $languageId = $context->resolveId('languages', (int) ($row['lang_id'] ?? 0));
            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'referrer_id' => $referrerId,
                'product_id' => $productId,
                'seller_id' => $sellerId,
                'language_id' => $languageId,
                'link_short' => LegacyValueCoercer::stringMax($row['link_short'] ?? null, 100),
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                DB::table('affiliate_links')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'affiliate_links', $legacyId, 'affiliate_links', $legacyId);
            $context->noteImported('affiliate_links');
        }
    }

    private function importEarnings(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable('affiliate_earnings') || ! $reader->hasTable('affiliate_earnings')) {
            return;
        }

        foreach ($reader->rows('affiliate_earnings') as $row) {
            $context->notePlanned('affiliate_earnings');

            $legacyId = (int) ($row['id'] ?? 0);
            $referrerId = $context->resolveId('users', (int) ($row['referrer_id'] ?? 0));
            $orderId = $context->resolveId('orders', (int) ($row['order_id'] ?? 0));

            if ($legacyId <= 0 || $referrerId === null || $orderId === null) {
                $context->noteSkipped('affiliate_earnings');

                continue;
            }

            $productId = $context->resolveId('products', (int) ($row['product_id'] ?? 0));
            $sellerId = $context->resolveId('users', (int) ($row['seller_id'] ?? 0));
            $createdAt = LegacyValueCoercer::date($row['created_at'] ?? now()) ?? now();

            $payload = [
                'id' => $legacyId,
                'referrer_id' => $referrerId,
                'order_id' => $orderId,
                'product_id' => $productId,
                'seller_id' => $sellerId,
                'commission_rate' => LegacyValueCoercer::decimal($row['commission_rate'] ?? 0),
                'earned_amount' => LegacyValueCoercer::decimal($row['earned_amount'] ?? 0),
                'currency_code' => LegacyValueCoercer::stringMax($row['currency'] ?? 'USD', 10, 'USD'),
                'exchange_rate' => LegacyValueCoercer::decimal($row['exchange_rate'] ?? 1),
                'legacy_id' => $legacyId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (! $context->dryRun) {
                DB::table('affiliate_earnings')->updateOrInsert(['id' => $legacyId], $payload);
            }

            $this->maps->remember($context, 'affiliate_earnings', $legacyId, 'affiliate_earnings', $legacyId);
            $context->noteImported('affiliate_earnings');
        }
    }
}
