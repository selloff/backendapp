<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyValueCoercer;
use App\Support\LegacyTextNormalizer;
use App\Modules\Selloff\Catalog\Models\ProductTranslation;
use App\Modules\Selloff\Catalog\Support\LegacyProductModerationMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductsLegacyImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'products';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('products')) {
            return;
        }

        $details = $this->detailsIndex($reader);

        foreach ($reader->rows('products') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $vendorLegacyId = (int) ($row['user_id'] ?? 0);
            $vendorId = $context->resolveId('users', $vendorLegacyId);
            if ($vendorId === null) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $categoryLegacyId = isset($row['category_id']) ? (int) $row['category_id'] : null;
            $categoryId = $categoryLegacyId ? $context->resolveId('categories', $categoryLegacyId) : null;

            $moderation = LegacyProductModerationMapper::fromLegacyRow($row);

            $payload = [
                'id' => $legacyId,
                'vendor_id' => $vendorId,
                'category_id' => $categoryId,
                'slug' => $row['slug'] ?? ('product-'.$legacyId),
                'sku' => $row['sku'] ?? ('LEGACY-'.$legacyId),
                'type' => $row['product_type'] ?? 'physical',
                'listing_type' => $row['listing_type'] ?? 'sell_on_site',
                'status' => $moderation['status'],
                'visibility' => LegacyValueCoercer::visibility($row['visibility'] ?? 'visible'),
                'is_active' => LegacyValueCoercer::bool($row['is_active'] ?? 1),
                'is_sold' => LegacyValueCoercer::bool($row['is_sold'] ?? 0),
                'is_verified' => $moderation['is_verified'],
                'is_affiliate' => LegacyValueCoercer::bool($row['is_affiliate'] ?? 0),
                'is_commission_set' => LegacyValueCoercer::bool($row['is_commission_set'] ?? 0),
                'commission_rate' => isset($row['commission_rate']) && $row['commission_rate'] !== ''
                    ? LegacyValueCoercer::decimal($row['commission_rate'])
                    : null,
                'reject_reason' => isset($row['reject_reason']) ? LegacyValueCoercer::stringMax($row['reject_reason'], 1000) : null,
                'is_edited' => LegacyValueCoercer::bool($row['is_edited'] ?? 0),
                'is_deleted' => LegacyValueCoercer::bool($row['is_deleted'] ?? 0),
                'is_draft' => LegacyValueCoercer::bool($row['is_draft'] ?? 0),
                'is_promoted' => LegacyValueCoercer::bool($row['is_promoted'] ?? 0),
                'promote_plan' => $row['promote_plan'] ?? null,
                'promoted_at' => LegacyValueCoercer::date($row['promote_start_date'] ?? null),
                'promoted_until' => LegacyValueCoercer::date($row['promote_end_date'] ?? null),
                'is_special_offer' => LegacyValueCoercer::bool($row['is_special_offer'] ?? 0),
                'special_offer_at' => LegacyValueCoercer::date($row['special_offer_date'] ?? null),
                'multiple_sale' => LegacyValueCoercer::bool($row['multiple_sale'] ?? 0),
                'price' => LegacyValueCoercer::decimal($row['price'] ?? 0),
                'price_discounted' => isset($row['price_discounted']) ? LegacyValueCoercer::decimal($row['price_discounted']) : null,
                'currency_code' => $row['currency'] ?? 'NGN',
                'stock' => (int) ($row['stock'] ?? 0),
                'pageviews' => max(0, (int) ($row['pageviews'] ?? 0)),
                'vat_rate' => isset($row['vat_rate']) && $row['vat_rate'] !== ''
                    ? LegacyValueCoercer::decimal($row['vat_rate'], 4)
                    : null,
                'is_free_product' => LegacyValueCoercer::bool($row['is_free_product'] ?? 0),
                'delivery_time_option_id' => $this->resolveDeliveryTimeOptionId($context, $row['shipping_delivery_time_id'] ?? null),
                'country_id' => $this->resolveLocationId($context, 'location_countries', $row['country_id'] ?? null),
                'state_id' => $this->resolveLocationId($context, 'location_states', $row['state_id'] ?? null),
                'city_id' => $this->resolveLocationId($context, 'location_cities', $row['city_id'] ?? null),
                'brand_id' => $this->resolveBrandId($context, $row['brand_id'] ?? null),
                'address' => isset($row['address']) ? LegacyValueCoercer::stringMax($row['address'], 500) : null,
                'zip_code' => isset($row['zip_code']) ? LegacyValueCoercer::stringMax($row['zip_code'], 50) : null,
                'legacy_id' => $legacyId,
                'created_at' => LegacyValueCoercer::date($row['created_at'] ?? now()),
                'updated_at' => LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? now()),
            ];

            $payload = array_merge($payload, $this->productBoostColumns($row));

            if (! $context->dryRun) {
                DB::table('products')->updateOrInsert(['id' => $legacyId], $payload);

                $detail = $details[$legacyId] ?? [];
                $title = LegacyValueCoercer::stringMax(
                    $detail['title'] ?? ucfirst(str_replace('-', ' ', (string) $payload['slug'])),
                    255,
                    'Product '.$legacyId,
                );
                ProductTranslation::query()->updateOrCreate(
                    ['product_id' => $legacyId, 'locale' => 'en'],
                    [
                        'title' => $title,
                        'description' => LegacyTextNormalizer::normalizeImportedText($detail['description'] ?? null),
                    ],
                );
            }

            $this->maps->remember($context, 'products', $legacyId, 'products', $legacyId);
            $context->noteImported($this->legacyTable());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function productBoostColumns(array $row): array
    {
        $columns = [];
        $promotedUntil = LegacyValueCoercer::date($row['promote_end_date'] ?? null);
        $promotedAt = LegacyValueCoercer::date($row['promote_start_date'] ?? null);
        $updatedAt = LegacyValueCoercer::date($row['updated_at'] ?? $row['created_at'] ?? null);
        $isPromoted = LegacyValueCoercer::bool($row['is_promoted'] ?? 0);
        $promotionActive = $isPromoted && ($promotedUntil === null || $promotedUntil->isFuture());

        if (Schema::hasColumn('products', 'last_bumped_at')) {
            $columns['last_bumped_at'] = $promotedAt ?? $updatedAt;
        }

        if (Schema::hasColumn('products', 'top_boost_active')) {
            $columns['top_boost_active'] = $promotionActive;
        }

        if (Schema::hasColumn('products', 'top_boost_expires_at') && $promotionActive) {
            $columns['top_boost_expires_at'] = $promotedUntil;
        }

        if (Schema::hasColumn('products', 'top_boost_weight') && $promotionActive) {
            $columns['top_boost_weight'] = max(1, (int) ($row['promote_day'] ?? 1));
        }

        return $columns;
    }

    /**
     * @return array<int, array{title?: string, description?: string}>
     */
    private function detailsIndex(MySqlDumpReader $reader): array
    {
        if (! $reader->hasTable('product_details')) {
            return [];
        }

        $index = [];
        foreach ($reader->rows('product_details') as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId > 0) {
                $index[$productId] = [
                    'title' => $row['title'] ?? null,
                    'description' => $row['description'] ?? null,
                ];
            }
        }

        return $index;
    }

    private function resolveLocationId(LegacyImportContext $context, string $legacyTable, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '' || (int) $legacyId <= 0) {
            return null;
        }

        return $context->resolveId($legacyTable, (int) $legacyId);
    }

    private function resolveBrandId(LegacyImportContext $context, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '' || (int) $legacyId <= 0) {
            return null;
        }

        return $context->resolveId('brands', (int) $legacyId);
    }

    private function resolveDeliveryTimeOptionId(LegacyImportContext $context, mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '' || (int) $legacyId <= 0) {
            return null;
        }

        return $context->resolveId('shipping_delivery_times', (int) $legacyId);
    }
}
