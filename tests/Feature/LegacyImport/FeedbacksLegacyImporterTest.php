<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\FeedbacksLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedbacksLegacyImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_imports_legacy_feedbacks_as_approved(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

        $dumpPath = storage_path('app/test-feedbacks-import.sql');
        file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `feedbacks` (
  `id` int NOT NULL,
  `vendor_id` int NOT NULL,
  `user_id` int NOT NULL,
  `rating` int DEFAULT NULL,
  `feedback_type` varchar(20) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `feedback` text,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `feedbacks` (`id`, `vendor_id`, `user_id`, `rating`, `feedback_type`, `title`, `feedback`, `created_at`, `updated_at`)
VALUES
(9001, 1, 2, 5, 'positive', NULL, 'Legacy imported positive feedback.', '2024-01-01 10:00:00', '2024-01-01 10:00:00');
SQL);

        $context = new LegacyImportContext(dryRun: false, tableFilter: 'feedbacks');
        $context->rememberMap('users', 1, 'users', $vendor->id);
        $context->rememberMap('users', 2, 'users', $buyer->id);

        DB::table('feedbacks')->where('vendor_id', $vendor->id)->where('user_id', $buyer->id)->delete();
        DB::table('feedbacks')->where('id', 9001)->delete();

        app(FeedbacksLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

        $row = DB::table('feedbacks')->where('id', 9001)->first();
        $this->assertNotNull($row);
        $this->assertSame('approved', $row->moderation_status);
        $this->assertSame('positive', $row->feedback_type);
        $this->assertSame((int) $vendor->id, (int) $row->vendor_id);
        $this->assertSame((int) $buyer->id, (int) $row->user_id);

        @unlink($dumpPath);
    }
}
