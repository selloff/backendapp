<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\AffiliateLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AffiliateLegacyImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_imports_affiliate_links_and_earnings(): void
    {
        $referrerId = (int) DB::table('users')->value('id');
        $sellerId = (int) DB::table('users')->orderByDesc('id')->value('id');
        $productId = (int) DB::table('products')->value('id');
        $orderId = (int) DB::table('orders')->value('id');
        $languageId = (int) DB::table('languages')->value('id');

        $this->assertNotNull($orderId, 'Demo seed should include at least one order.');

        $dumpPath = storage_path('app/test-affiliate-import.sql');
        file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `affiliate_links` (
  `id` int NOT NULL,
  `referrer_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `seller_id` int DEFAULT NULL,
  `lang_id` int DEFAULT NULL,
  `link_short` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `affiliate_links` (`id`, `referrer_id`, `product_id`, `seller_id`, `lang_id`, `link_short`, `created_at`)
VALUES
(1,21323,10338,28,1,'68d6ec4fb5a54','2025-09-26 20:41:03');

CREATE TABLE `affiliate_earnings` (
  `id` int NOT NULL,
  `referrer_id` int DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `seller_id` int DEFAULT NULL,
  `commission_rate` tinyint DEFAULT NULL,
  `earned_amount` decimal(12,2) DEFAULT '0.00',
  `currency` varchar(20) DEFAULT 'USD',
  `exchange_rate` double DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `affiliate_earnings` (`id`, `referrer_id`, `order_id`, `product_id`, `seller_id`, `commission_rate`, `earned_amount`, `currency`, `exchange_rate`, `created_at`)
VALUES
(1,21323,501,10338,28,5,12.50,'NGN',1.0,'2025-09-27 10:00:00');
SQL);

        $context = new LegacyImportContext(dryRun: false);
        $context->rememberMap('users', 21323, 'users', $referrerId);
        $context->rememberMap('users', 28, 'users', $sellerId);
        $context->rememberMap('products', 10338, 'products', $productId);
        $context->rememberMap('orders', 501, 'orders', $orderId);
        $context->rememberMap('languages', 1, 'languages', $languageId);

        app(AffiliateLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

        $this->assertDatabaseHas('affiliate_links', [
            'id' => 1,
            'referrer_id' => $referrerId,
            'product_id' => $productId,
            'seller_id' => $sellerId,
            'language_id' => $languageId,
            'link_short' => '68d6ec4fb5a54',
            'legacy_id' => 1,
        ]);

        $this->assertDatabaseHas('affiliate_earnings', [
            'id' => 1,
            'referrer_id' => $referrerId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'seller_id' => $sellerId,
            'earned_amount' => '12.50',
            'currency_code' => 'NGN',
            'legacy_id' => 1,
        ]);

        @unlink($dumpPath);
    }
}
