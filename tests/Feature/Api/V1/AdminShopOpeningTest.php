<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin lists shop opening requests with status filter', function () {
    config(['selloff.legacy_media_public_url' => 'https://selloff.ng']);

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update([
        'vendor_documents' => [
            ['name' => 'National ID', 'path' => 'uploads/support/file_demo.jpg'],
        ],
    ]);
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
        ])
        ->assertJsonPath(
            'data.data.0.vendor_documents.0.url',
            'https://selloff.ng/uploads/support/file_demo.jpg',
        );

    $this->getJson('/api/v1/admin/shop-opening/requests?status=2')
        ->assertOk()
        ->assertJsonPath('data.total', 0);
});

test('admin can approve and reject shop opening requests', function () {
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
    expect($buyer->shop_opening_status)->toBe(0);
    expect($buyer->hasRole('vendor'))->toBeTrue();
});

test('legacy importer maps shop opening fields', function () {
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
    expect($pending->shop_opening_status)->toBe(1);
    expect($pending->vendor_documents)->toHaveCount(2);
    expect($pending->shop_request_date)->not->toBeNull();

    $rejected = User::query()->findOrFail(502);
    expect($rejected->shop_opening_status)->toBe(2);
    expect($rejected->shop_opening_rejection_reason)->toBe('Missing proof of address');

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
});

test('admin can view shop opening documents inline through authenticated proxy', function () {
    config(['selloff.media_disk' => 'public']);
    Storage::fake('public');

    $path = 'uploads/support/id-card.jpg';
    Storage::disk('public')->put($path, 'fake-image-content');

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update([
        'vendor_documents' => [
            ['name' => 'ID card', 'path' => $path],
        ],
    ]);

    Sanctum::actingAs($admin);

    $this->get('/api/v1/admin/shop-opening/requests/'.$buyer->id.'/documents/view?path='.urlencode($path))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="ID card"');

    $this->get('/api/v1/admin/shop-opening/requests/'.$buyer->id.'/documents/view?path='.urlencode('uploads/support/missing.jpg'))
        ->assertNotFound();
});

test('admin can view shop opening documents stored on s3 with legacy storage alias', function () {
    Storage::fake('s3');
    config(['selloff.media_disk' => 's3']);

    $path = 'uploads/support/file_68b31eba183fe7-63101679-38061330.jpg';
    Storage::disk('s3')->put($path, 'fake-image-content');

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update([
        'vendor_documents' => [
            [
                'name' => 'National ID',
                'path' => $path,
                'storage' => 'aws_s3',
            ],
        ],
    ]);

    Sanctum::actingAs($admin);

    $this->get('/api/v1/admin/shop-opening/requests/'.$buyer->id.'/documents/view?path='.urlencode($path))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="National ID"');
});

test('admin can view shop opening document when only legacy dated s3 key exists', function () {
    Storage::fake('s3');
    config(['selloff.media_disk' => 's3']);

    $filename = 'file_68b13dafe67e48-18174823-39576584.jpg';
    $dbPath = 'uploads/support/'.$filename;
    $storagePath = 'uploads/support/202607/'.$filename;
    Storage::disk('s3')->put($storagePath, 'fake-image-content');

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update([
        'vendor_documents' => [
            ['name' => 'National ID', 'path' => $dbPath, 'storage' => 'aws_s3'],
        ],
    ]);

    Sanctum::actingAs($admin);

    $this->get('/api/v1/admin/shop-opening/requests/'.$buyer->id.'/documents/view?path='.urlencode($dbPath))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename="National ID"');
});

test('admin can view shop opening document via legacy media public url fallback', function () {
    Storage::fake('public');
    Storage::fake('s3');
    config([
        'selloff.media_disk' => 'public',
        'selloff.legacy_media_public_url' => 'https://selloff.ng',
    ]);

    $filename = 'file_68b13dafe67e48-18174823-39576584.jpg';
    $dbPath = 'uploads/support/'.$filename;

    Http::fake([
        'https://selloff.ng/*' => Http::response('fake-image-content', 200, ['Content-Type' => 'image/jpeg']),
        '*' => Http::response('', 404),
    ]);

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $buyer->update([
        'vendor_documents' => [
            ['name' => 'National ID', 'path' => $dbPath],
        ],
    ]);

    Sanctum::actingAs($admin);

    $this->get('/api/v1/admin/shop-opening/requests/'.$buyer->id.'/documents/view?path='.urlencode($dbPath))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg')
        ->assertHeader('content-disposition', 'inline; filename="National ID"');
});
