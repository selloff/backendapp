<?php

use App\LegacyImport\Importers\FeedbacksLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('imports legacy feedbacks as approved', function () {
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
    expect($row)->not->toBeNull();
    expect($row->moderation_status)->toBe('approved');
    expect($row->feedback_type)->toBe('positive');
    expect((int) $row->vendor_id)->toBe((int) $vendor->id);
    expect((int) $row->user_id)->toBe((int) $buyer->id);

    @unlink($dumpPath);
});
