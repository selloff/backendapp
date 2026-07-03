<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminShopOpeningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_lists_shop_opening_requests_with_status_filter(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/shop-opening/requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $buyer->id)
            ->assertJsonPath('data.data.0.shop_opening_status', 1)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        [
                            'id',
                            'shop_name',
                            'location_label',
                            'vendor_documents',
                            'shop_opening_status',
                        ],
                    ],
                    'total',
                    'current_page',
                    'last_page',
                ],
            ]);

        $this->getJson('/api/v1/admin/shop-opening/requests?status=2')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_admin_can_approve_and_reject_shop_opening_requests(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/shop-opening/requests/{$buyer->id}/reject", [
            'status' => 2,
            'reason' => 'Incomplete documents',
        ])
            ->assertOk()
            ->assertJsonPath('data.shop_opening_status', 2)
            ->assertJsonPath('data.shop_opening_rejection_reason', 'Incomplete documents');

        $this->postJson("/api/v1/admin/shop-opening/requests/{$buyer->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.shop_opening_status', 0);

        $buyer->refresh();
        $this->assertSame(0, $buyer->shop_opening_status);
        $this->assertTrue($buyer->hasRole('vendor'));
    }

    public function test_legacy_importer_maps_shop_opening_fields(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'shop-opening-users-dump');
        $documents = addslashes(serialize([
            ['name' => 'ID card', 'path' => 'uploads/support/id.jpg'],
            ['name' => 'Selfie', 'path' => 'uploads/support/selfie.jpg'],
        ]));

        file_put_contents($path, <<<SQL
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `email_status` tinyint DEFAULT 0,
  `password` varchar(255) DEFAULT NULL,
  `role_id` int DEFAULT 3,
  `balance` decimal(13,2) DEFAULT 0.00,
  `banned` tinyint DEFAULT 0,
  `first_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `about_me` text,
  `country_id` int DEFAULT NULL,
  `state_id` int DEFAULT NULL,
  `city_id` int DEFAULT NULL,
  `is_active_shop_request` tinyint DEFAULT 0,
  `vendor_documents` text,
  `shop_request_date` datetime DEFAULT NULL,
  `shop_request_reject_reason` text,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `users` (`id`, `username`, `slug`, `email`, `email_status`, `password`, `role_id`, `balance`, `banned`, `first_name`, `last_name`, `phone_number`, `about_me`, `is_active_shop_request`, `vendor_documents`, `shop_request_date`, `shop_request_reject_reason`, `created_at`, `updated_at`)
VALUES
(501,'Pending Shop','pending-shop','pending-shop@example.com',1,'hash',3,0,0,'Pending','Seller','+2348000000001','Selling gadgets',1,'{$documents}','2024-05-01 10:00:00',NULL,'2024-05-01 10:00:00','2024-05-01 10:00:00'),
(502,'Rejected Shop','rejected-shop','rejected-shop@example.com',1,'hash',3,0,0,'Rejected','Seller','+2348000000002','Rejected shop',2,'{$documents}','2024-04-01 10:00:00','Missing proof of address','2024-04-01 10:00:00','2024-04-01 10:00:00');
SQL);

        $this->artisan('selloff:migrate', ['--fresh' => true]);
        $this->artisan('selloff:import-legacy-data', ['--source' => $path])->assertSuccessful();
        unlink($path);

        $pending = User::query()->findOrFail(501);
        $this->assertSame(1, $pending->shop_opening_status);
        $this->assertCount(2, $pending->vendor_documents);
        $this->assertNotNull($pending->shop_request_date);

        $rejected = User::query()->findOrFail(502);
        $this->assertSame(2, $rejected->shop_opening_status);
        $this->assertSame('Missing proof of address', $rejected->shop_opening_rejection_reason);

        $admin = User::factory()->create();
        $admin->syncRoles(['super-admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/shop-opening/requests')
            ->assertOk()
            ->assertJsonPath('data.total', 2);

        $this->getJson('/api/v1/admin/shop-opening/requests?status=1')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', 501);
    }
}
