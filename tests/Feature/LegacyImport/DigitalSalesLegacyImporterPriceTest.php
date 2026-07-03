<?php

namespace Tests\Feature\LegacyImport;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DigitalSalesLegacyImporterPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_digital_sales_import_preserves_price_and_currency(): void
    {
        $fixture = $this->writeFixture();

        $this->artisan('selloff:import-legacy-data', ['--source' => $fixture])->assertSuccessful();

        $this->assertDatabaseHas('digital_sales', [
            'legacy_id' => 9001,
            'price' => '5000.00',
            'currency_code' => 'NGN',
            'purchase_code' => 'PURCHASE-9001',
        ]);
    }

    private function writeFixture(): string
    {
        $path = storage_path('framework/testing/digital-sales-price.sql');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, <<<'SQL'
CREATE TABLE `digital_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `product_id` int NOT NULL,
  `product_title` varchar(500) DEFAULT NULL,
  `seller_id` int NOT NULL,
  `buyer_id` int NOT NULL,
  `license_key` varchar(255) DEFAULT NULL,
  `purchase_code` varchar(100) DEFAULT NULL,
  `currency` varchar(20) NOT NULL DEFAULT 'NGN',
  `price` decimal(13,2) DEFAULT '0.00',
  `purchase_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `digital_sales` (`id`, `order_id`, `product_id`, `product_title`, `seller_id`, `buyer_id`, `license_key`, `purchase_code`, `currency`, `price`, `purchase_date`, `created_at`) VALUES
(9001, 0, 0, 'Legacy digital product', 0, 0, '', 'PURCHASE-9001', 'NGN', 5000.00, '2024-05-30 11:56:05', '2024-05-30 11:56:05');
SQL);

        return $path;
    }

    protected function tearDown(): void
    {
        $path = storage_path('framework/testing/digital-sales-price.sql');
        if (file_exists($path)) {
            unlink($path);
        }

        parent::tearDown();
    }
}
