<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin lists account deletion requests', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update(['account_delete_requested_at' => now()]);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/account-deletion-requests')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['email' => 'buyer@selloff.test'])
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    [
                        'id',
                        'email',
                        'account_delete_requested_at',
                        'primary_role',
                    ],
                ],
                'total',
                'current_page',
                'last_page',
            ],
        ]);
});

test('legacy importer maps account deletion request fields', function () {
    $path = tempnam(sys_get_temp_dir(), 'account-delete-users-dump');

    file_put_contents($path, <<<'SQL'
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
  `account_delete_req` tinyint DEFAULT 0,
  `account_delete_req_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `users` (`id`, `username`, `slug`, `email`, `email_status`, `password`, `role_id`, `balance`, `banned`, `first_name`, `last_name`, `account_delete_req`, `account_delete_req_date`, `created_at`, `updated_at`)
VALUES
(601,'Delete Me','delete-me','delete-me@example.com',1,'hash',3,0,0,'Delete','Me',1,'2024-06-15 12:00:00','2024-06-01 10:00:00','2024-06-15 12:00:00'),
(602,'Keep Me','keep-me','keep-me@example.com',1,'hash',3,0,0,'Keep','Me',0,NULL,'2024-06-01 10:00:00','2024-06-01 10:00:00');
SQL);

    $this->artisan('selloff:migrate', ['--fresh' => true]);
    $this->artisan('selloff:import-legacy-data', ['--source' => $path])->assertSuccessful();
    unlink($path);

    $pending = User::query()->findOrFail(601);
    expect($pending->account_delete_requested_at)->not->toBeNull();
    expect($pending->account_delete_requested_at?->format('Y-m-d H:i:s'))->toBe('2024-06-15 12:00:00');

    $active = User::query()->findOrFail(602);
    expect($active->account_delete_requested_at)->toBeNull();

    $admin = User::factory()->create();
    $admin->syncRoles(['super-admin']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/account-deletion-requests')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', 601);
});
