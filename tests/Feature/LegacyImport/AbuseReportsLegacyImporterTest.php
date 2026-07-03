<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\AbuseReportsLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AbuseReportsLegacyImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_imports_legacy_seller_and_review_abuse_reports(): void
    {
        $sellerId = DB::table('users')->insertGetId([
            'username' => 'reported-seller',
            'slug' => 'reported-seller',
            'email' => 'reported-seller@example.test',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reporterId = DB::table('users')->insertGetId([
            'username' => 'reporter-user',
            'slug' => 'reporter-user',
            'email' => 'reporter-user@example.test',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->value('id');

        $reviewId = DB::table('product_reviews')->insertGetId([
            'product_id' => $productId,
            'user_id' => $reporterId,
            'rating' => 2,
            'review' => 'Imported review body',
            'is_approved' => true,
            'legacy_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $maps = app(LegacyImportMapRepository::class);
        $seedContext = new LegacyImportContext(dryRun: false);
        $maps->remember($seedContext, 'users', 13010, 'users', $sellerId);
        $maps->remember($seedContext, 'users', 14721, 'users', $reporterId);
        $maps->remember($seedContext, 'reviews', 2, 'product_reviews', $reviewId);

        $dumpPath = storage_path('app/test-abuse-reports-import.sql');
        file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `abuse_reports` (
  `id` int NOT NULL,
  `item_type` varchar(50) NOT NULL,
  `item_id` int NOT NULL,
  `report_user_id` int NOT NULL,
  `description` text,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `abuse_reports` (`id`, `item_type`, `item_id`, `report_user_id`, `description`, `created_at`)
VALUES
(1,'seller',13010,14721,'Seller scam report','2024-05-23 12:50:17'),
(2,'review',2,13010,'Review abuse report','2024-05-24 11:38:01');
SQL);

        $context = new LegacyImportContext(dryRun: false, tableFilter: 'abuse_reports');
        $maps->hydrateContext($context);

        app(AbuseReportsLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

        $this->assertDatabaseHas('abuse_reports', [
            'id' => 1,
            'report_type' => 'seller',
            'user_id' => $sellerId,
            'item_id' => $sellerId,
            'reporter_id' => $reporterId,
            'description' => 'Seller scam report',
        ]);

        $this->assertDatabaseHas('abuse_reports', [
            'id' => 2,
            'report_type' => 'review',
            'item_id' => $reviewId,
            'reporter_id' => $sellerId,
            'description' => 'Review abuse report',
        ]);

        @unlink($dumpPath);
    }
}
