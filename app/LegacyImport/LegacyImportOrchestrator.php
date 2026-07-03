<?php

namespace App\LegacyImport;

use App\LegacyImport\Importers\AbuseReportsLegacyImporter;
use App\LegacyImport\Importers\AffiliateLegacyImporter;
use App\LegacyImport\Importers\BrandsLegacyImporter;
use App\LegacyImport\Importers\CategoriesLegacyImporter;
use App\LegacyImport\Importers\CatalogDepthLegacyImporter;
use App\LegacyImport\Importers\CommerceDepthLegacyImporter;
use App\LegacyImport\Importers\CmsLegacyImporter;
use App\LegacyImport\Importers\ContentLegacyImporter;
use App\LegacyImport\Importers\CouponsLegacyImporter;
use App\LegacyImport\Importers\CurrenciesLanguagesLegacyImporter;
use App\LegacyImport\Importers\EscrowTransactionsLegacyImporter;
use App\LegacyImport\Importers\FeedbacksLegacyImporter;
use App\LegacyImport\Importers\LegacyImporter;
use App\LegacyImport\Importers\LocationLegacyImporter;
use App\LegacyImport\Importers\MediaLegacyImporter;
use App\LegacyImport\Importers\MessagingLegacyImporter;
use App\LegacyImport\Importers\MultiTableLegacyImporter;
use App\LegacyImport\Importers\NewsletterSubscribersLegacyImporter;
use App\LegacyImport\Importers\NonTransactionalLegacyImporter;
use App\LegacyImport\Importers\OrdersLegacyImporter;
use App\LegacyImport\Importers\PayoutsLegacyImporter;
use App\LegacyImport\Importers\PaymentDepthLegacyImporter;
use App\LegacyImport\Importers\PlatformSettingsLegacyImporter;
use App\LegacyImport\Importers\ProductsLegacyImporter;
use App\LegacyImport\Importers\ReferralProfilesLegacyImporter;
use App\LegacyImport\Importers\RefundRequestsLegacyImporter;
use App\LegacyImport\Importers\ReviewsLegacyImporter;
use App\LegacyImport\Importers\RolesPermissionsLegacyImporter;
use App\LegacyImport\Importers\RoutesLegacyImporter;
use App\LegacyImport\Importers\ShippingAddressesLegacyImporter;
use App\LegacyImport\Importers\ShippingZonesLegacyImporter;
use App\LegacyImport\Importers\SocialLegacyImporter;
use App\LegacyImport\Importers\SupportLegacyImporter;
use App\LegacyImport\Importers\TransactionsLegacyImporter;
use App\LegacyImport\Importers\UserDepthLegacyImporter;
use App\LegacyImport\Importers\UsersLegacyImporter;
use App\LegacyImport\Importers\VendorListingPerformanceLegacyImporter;
use App\LegacyImport\Importers\VendorEarningsLegacyImporter;
use App\LegacyImport\Importers\WalletDepositsLegacyImporter;
use App\LegacyImport\Importers\WalletExpensesLegacyImporter;
use App\LegacyImport\Importers\WishlistLegacyImporter;
use App\LegacyImport\Support\LegacyImportConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyImportOrchestrator
{
    /** @var list<string> */
    private const AUXILIARY_LEGACY_TABLES = [
        'category_lang',
        'product_details',
        'order_items',
        'shipping_zone_locations',
        'shipping_zone_methods',
        'shipping_delivery_times',
        'brand_lang',
        'coupon_products',
        'coupons_used',
        'media',
        'languages',
        'location_states',
        'location_cities',
    ];

    /**
     * @return list<LegacyImporter>
     */
    private function importers(): array
    {
        return [
            app(CategoriesLegacyImporter::class),
            app(LocationLegacyImporter::class),
            app(CurrenciesLanguagesLegacyImporter::class),
            app(RolesPermissionsLegacyImporter::class),
            app(UsersLegacyImporter::class),
            app(UserDepthLegacyImporter::class),
            app(ReferralProfilesLegacyImporter::class),
            app(ShippingAddressesLegacyImporter::class),
            app(BrandsLegacyImporter::class),
            app(ProductsLegacyImporter::class),
            app(CatalogDepthLegacyImporter::class),
            app(MediaLegacyImporter::class),
            app(WishlistLegacyImporter::class),
            app(OrdersLegacyImporter::class),
            app(AffiliateLegacyImporter::class),
            app(CommerceDepthLegacyImporter::class),
            app(TransactionsLegacyImporter::class),
            app(RefundRequestsLegacyImporter::class),
            app(WalletDepositsLegacyImporter::class),
            app(WalletExpensesLegacyImporter::class),
            app(CouponsLegacyImporter::class),
            app(PaymentDepthLegacyImporter::class),
            app(VendorEarningsLegacyImporter::class),
            app(PayoutsLegacyImporter::class),
            app(ShippingZonesLegacyImporter::class),
            app(EscrowTransactionsLegacyImporter::class),
            app(FeedbacksLegacyImporter::class),
            app(ReviewsLegacyImporter::class),
            app(SocialLegacyImporter::class),
            app(AbuseReportsLegacyImporter::class),
            app(MessagingLegacyImporter::class),
            app(ContentLegacyImporter::class),
            app(CmsLegacyImporter::class),
            app(SupportLegacyImporter::class),
            app(NewsletterSubscribersLegacyImporter::class),
            app(RoutesLegacyImporter::class),
            app(PlatformSettingsLegacyImporter::class),
            app(VendorListingPerformanceLegacyImporter::class),
        ];
    }

    /**
     * @return list<string>
     */
    public function coveredLegacyTables(): array
    {
        $tables = self::AUXILIARY_LEGACY_TABLES;

        foreach ($this->importers() as $importer) {
            $tables = array_merge($tables, $this->importerTables($importer));
        }

        return array_values(array_unique($tables));
    }

    public function run(LegacyImportContext $context, MySqlDumpReader $reader): LegacyImportContext
    {
        if (! $context->dryRun) {
            app(LegacyImportMapRepository::class)->hydrateContext($context);
        }

        $excludedImporters = LegacyImportConfig::excludedImporters();

        foreach ($this->importers() as $importer) {
            if (in_array(class_basename($importer), $excludedImporters, true)) {
                continue;
            }

            if (! $this->shouldRunImporter($importer, $context, $reader)) {
                continue;
            }

            $startedAt = microtime(true);

            $runImporter = function () use ($importer, $context, $reader): void {
                $importer->import($context, $reader);
            };

            if ($importer instanceof NonTransactionalLegacyImporter) {
                $runImporter();
            } else {
                DB::transaction($runImporter, attempts: 3);
            }

            if ($context->profile) {
                $context->noteTiming(class_basename($importer), microtime(true) - $startedAt);
            }
        }

        if (! $context->dryRun) {
            $this->syncSequences([
                'users',
                'categories',
                'products',
                'orders',
                'order_items',
                'countries',
                'states',
                'cities',
                'currencies',
                'languages',
                'shipping_addresses',
                'brands',
                'product_images',
                'product_options',
                'product_option_values',
                'product_variants',
                'custom_fields',
                'custom_field_options',
                'digital_files',
                'product_license_keys',
                'tags',
                'invoices',
                'quote_requests',
                'digital_sales',
                'coupon_usages',
                'tax_rules',
                'bank_transfer_requests',
                'membership_plans',
                'membership_transactions',
                'user_membership_plans',
                'promotion_transactions',
                'login_activities',
                'payment_transactions',
                'wallet_deposits',
                'wallet_transactions',
                'refund_requests',
                'refund_messages',
                'affiliate_links',
                'affiliate_earnings',
                'coupons',
                'vendor_earnings',
                'payout_requests',
                'shipping_zones',
                'shipping_zone_locations',
                'shipping_methods',
                'escrow_transactions',
                'feedbacks',
                'product_reviews',
                'followers',
                'comments',
                'conversations',
                'messages',
                'blog_categories',
                'blog_posts',
                'pages',
                'knowledge_base_categories',
                'knowledge_base_articles',
                'support_tickets',
                'support_messages',
                'contact_messages',
                'abuse_reports',
                'route_slugs',
                'newsletter_subscribers',
                'blog_comments',
                'sliders',
                'homepage_banners',
                'ad_spaces',
                'delivery_time_options',
            ]);
            app(LegacyImportPostProcessor::class)->run();
        }

        return $context;
    }

    private function shouldRunImporter(LegacyImporter $importer, LegacyImportContext $context, MySqlDumpReader $reader): bool
    {
        foreach ($this->importerTables($importer) as $table) {
            if (! $reader->hasTable($table)) {
                continue;
            }

            if ($context->tableFilter === null || $context->tableFilter === $table) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function importerTables(LegacyImporter $importer): array
    {
        if ($importer instanceof MultiTableLegacyImporter) {
            return $importer->legacyTables();
        }

        return [$importer->legacyTable()];
    }

    /**
     * @param list<string> $tables
     */
    private function syncSequences(array $tables): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $sequence = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS seq', [$table, 'id']);
            if (! $sequence?->seq) {
                continue;
            }

            $maxId = DB::table($table)->max('id') ?? 1;
            DB::selectOne('SELECT setval(?, ?, true) AS value', [$sequence->seq, $maxId]);
        }
    }
}
