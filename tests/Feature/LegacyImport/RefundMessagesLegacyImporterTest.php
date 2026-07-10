<?php

use App\LegacyImport\Importers\RefundRequestsLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('imports refund request messages', function () {
    $buyerId = (int) DB::table('users')->value('id');
    $sellerId = (int) DB::table('users')->orderByDesc('id')->value('id');
    $orderId = (int) DB::table('orders')->value('id');

    $dumpPath = storage_path('app/test-refund-messages-import.sql');
    file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `refund_requests` (
  `id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `buyer_id` int DEFAULT NULL,
  `seller_id` int DEFAULT NULL,
  `description` text,
  `status` int DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `refund_requests` (`id`, `order_id`, `buyer_id`, `seller_id`, `description`, `status`, `created_at`, `updated_at`)
VALUES
(10,300,100,200,'Damaged item',0,'2024-06-01 10:00:00','2024-06-01 10:00:00');

CREATE TABLE `refund_requests_messages` (
  `id` int NOT NULL,
  `request_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `is_buyer` tinyint(1) NOT NULL DEFAULT '1',
  `message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `refund_requests_messages` (`id`, `request_id`, `user_id`, `is_buyer`, `message`, `created_at`)
VALUES
(1,10,100,1,'Please refund this order','2024-06-01 11:00:00'),
(2,10,200,0,'We will review your request','2024-06-01 12:00:00');
SQL);

    $context = new LegacyImportContext(dryRun: false);
    $context->rememberMap('users', 100, 'users', $buyerId);
    $context->rememberMap('users', 200, 'users', $sellerId);
    $context->rememberMap('orders', 300, 'orders', $orderId);

    app(RefundRequestsLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

    $this->assertDatabaseHas('refund_messages', [
        'id' => 1,
        'refund_request_id' => 10,
        'user_id' => $buyerId,
        'is_admin' => false,
        'message' => 'Please refund this order',
        'legacy_id' => 1,
    ]);

    $this->assertDatabaseHas('refund_messages', [
        'id' => 2,
        'refund_request_id' => 10,
        'user_id' => $sellerId,
        'is_admin' => true,
        'message' => 'We will review your request',
        'legacy_id' => 2,
    ]);

    @unlink($dumpPath);
});
