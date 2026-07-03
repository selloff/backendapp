<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\SocialLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCommentsLegacyImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_imports_guest_name_email_and_ip_address(): void
    {
        $productId = (int) DB::table('products')->value('id');

        $maps = app(LegacyImportMapRepository::class);
        $seedContext = new LegacyImportContext(dryRun: false);
        $maps->remember($seedContext, 'products', 99, 'products', $productId);

        $dumpPath = storage_path('app/test-product-comments-import.sql');
        file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `comments` (
  `id` int NOT NULL,
  `parent_id` int DEFAULT '0',
  `product_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `comment` varchar(5000) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `comments` (`id`, `parent_id`, `product_id`, `user_id`, `email`, `name`, `comment`, `ip_address`, `status`, `created_at`)
VALUES
(1,0,99,0,'philchima@gmail.com','Philip Chimaobi onwunaruwa','Need replacement neck','102.88.36.178',0,'2023-12-10 20:43:54');
SQL);

        $context = new LegacyImportContext(dryRun: false, tableFilter: 'comments');
        $maps->hydrateContext($context);

        app(SocialLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

        $this->assertDatabaseHas('comments', [
            'id' => 1,
            'product_id' => $productId,
            'name' => 'Philip Chimaobi onwunaruwa',
            'email' => 'philchima@gmail.com',
            'ip_address' => '102.88.36.178',
            'is_approved' => false,
            'comment' => 'Need replacement neck',
        ]);

        @unlink($dumpPath);
    }
}
