<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Models\AdminNotificationRead;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin notifications require authentication', function () {
    $this->getJson('/api/v1/admin/notifications')->assertUnauthorized();
});

test('admin notifications return grouped pending items', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/notifications')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'unread_count',
                'groups' => [
                    '*' => [
                        'type',
                        'label',
                        'list_url',
                        'unread_count',
                        'total_count',
                        'items' => [
                            '*' => [
                                'key',
                                'title',
                                'body',
                                'created_at',
                                'is_read',
                                'action_url',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    $pendingGroup = collect($response->json('data.groups'))
        ->firstWhere('type', 'pending_product');

    expect($pendingGroup)->not->toBeNull();
    expect(collect($pendingGroup['items'])->pluck('key'))->toContain('pending_product:'.$pending->id);
    expect($response->json('data.unread_count'))->toBeGreaterThanOrEqual(1);
});

test('pending products group appears first', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $types = collect($this->getJson('/api/v1/admin/notifications')->json('data.groups'))
        ->pluck('type')
        ->values()
        ->all();

    $pendingIndex = array_search('pending_product', $types, true);
    $editedIndex = array_search('edited_product', $types, true);

    $this->assertNotFalse($pendingIndex);
    if ($editedIndex !== false) {
        expect($pendingIndex)->toBeLessThan($editedIndex);
    }
});

test('group total count covers full queue not only visible items', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $pendingGroup = collect($this->getJson('/api/v1/admin/notifications')->json('data.groups'))
        ->firstWhere('type', 'pending_product');

    expect($pendingGroup)->not->toBeNull();
    expect($pendingGroup['total_count'])->toBeGreaterThanOrEqual(count($pendingGroup['items']));
    expect(count($pendingGroup['items']))->toBeLessThanOrEqual(15);
    expect($pendingGroup['unread_count'])->toBeLessThanOrEqual($pendingGroup['total_count']);
    foreach ($pendingGroup['items'] as $item) {
        expect($item['is_read'])->toBeFalse();
    }
});

test('mark read keeps item visible with is_read true', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
    Sanctum::actingAs($admin);

    $key = 'pending_product:'.$pending->id;
    $before = $this->getJson('/api/v1/admin/notifications')->json('data.unread_count');

    $this->postJson('/api/v1/admin/notifications/'.rawurlencode($key).'/read')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('admin_notification_reads', [
        'notification_key' => $key,
        'read_by_user_id' => $admin->id,
    ]);

    $after = $this->getJson('/api/v1/admin/notifications')->json('data.unread_count');
    expect($after)->toBe($before - 1);

    $response = $this->getJson('/api/v1/admin/notifications')->assertOk();
    $item = collect($response->json('data.groups'))
        ->flatMap(fn (array $group): array => $group['items'])
        ->firstWhere('key', $key);

    expect($item)->not->toBeNull();
    expect($item['is_read'])->toBeTrue();
});

test('shared read state shows item as read for all admins', function () {
    $adminA = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $role = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $adminB = User::query()->create([
        'first_name' => 'Second',
        'last_name' => 'Admin',
        'slug' => 'second-admin-notifications',
        'email' => 'second-admin-notifications@selloff.test',
        'password' => Hash::make('password'),
        'is_enable_login' => true,
        'is_disable' => false,
        'email_verified_at' => now(),
    ]);
    $adminB->syncRoles([$role]);
    $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
    $key = 'pending_product:'.$pending->id;

    Sanctum::actingAs($adminA);
    $this->postJson('/api/v1/admin/notifications/'.rawurlencode($key).'/read')->assertOk();

    Sanctum::actingAs($adminB);
    $item = collect($this->getJson('/api/v1/admin/notifications')->json('data.groups'))
        ->flatMap(fn (array $group): array => $group['items'])
        ->firstWhere('key', $key);

    expect($item)->not->toBeNull();
    expect($item['is_read'])->toBeTrue();
    expect(AdminNotificationRead::query()->where('notification_key', $key)->count())->toBe(1);
});

test('limited admin omits gated notification types', function () {
    $role = Role::query()->firstOrCreate(['name' => 'products-only-notifications', 'guard_name' => 'web']);
    $role->syncPermissions(['admin_panel', 'products']);

    $user = User::query()->create([
        'first_name' => 'Products',
        'last_name' => 'Only',
        'slug' => 'products-only-notifications',
        'email' => 'products-only-notifications@selloff.test',
        'password' => Hash::make('password'),
        'is_enable_login' => true,
        'is_disable' => false,
        'email_verified_at' => now(),
    ]);
    $user->syncRoles([$role]);

    Sanctum::actingAs($user);

    $types = collect($this->getJson('/api/v1/admin/notifications')->json('data.groups'))
        ->pluck('type');

    expect($types->contains('pending_product'))->toBeTrue();
    expect($types->contains('abuse_report'))->toBeFalse();
});

test('mark read returns not found for invalid key', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/notifications/not-a-valid-key/read')
        ->assertNotFound();
});

test('unread count endpoint returns count', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $listCount = $this->getJson('/api/v1/admin/notifications')->json('data.unread_count');
    $badgeCount = $this->getJson('/api/v1/admin/notifications/unread-count')
        ->assertOk()
        ->json('data.count');

    expect($badgeCount)->toBe($listCount);
});

test('mark all read marks visible notifications but keeps them listed', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $before = $this->getJson('/api/v1/admin/notifications')->json('data.unread_count');
    expect($before)->toBeGreaterThan(0);

    $this->postJson('/api/v1/admin/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('success', true);

    $response = $this->getJson('/api/v1/admin/notifications')->assertOk();
    expect($response->json('data.unread_count'))->toBe(0);
    expect($response->json('data.groups'))->not->toBeEmpty();

    foreach ($response->json('data.groups') as $group) {
        foreach ($group['items'] as $item) {
            expect($item['is_read'])->toBeTrue();
        }
    }
});

test('notifications older than 30 days are excluded', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $pending = Product::query()->where('sku', 'DEMO-PENDING-1')->firstOrFail();
    $pending->created_at = now()->subDays(31);
    $pending->save();

    Sanctum::actingAs($admin);

    $keys = collect($this->getJson('/api/v1/admin/notifications')->json('data.groups'))
        ->flatMap(fn (array $group): array => $group['items'])
        ->pluck('key');

    expect($keys->contains('pending_product:'.$pending->id))->toBeFalse();
});
