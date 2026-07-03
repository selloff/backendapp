<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\AdminNotificationRead;
use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_notifications_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/notifications')->assertUnauthorized();
    }

    public function test_admin_notifications_return_grouped_pending_items(): void
    {
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

        $this->assertNotNull($pendingGroup);
        $this->assertSame(
            'pending_product:'.$pending->id,
            collect($pendingGroup['items'])->pluck('key')->first(),
        );
        $this->assertGreaterThanOrEqual(1, $response->json('data.unread_count'));
    }

    public function test_pending_products_group_appears_first(): void
    {
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
            $this->assertLessThan($editedIndex, $pendingIndex);
        }
    }

    public function test_group_total_count_covers_full_queue_not_only_visible_items(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $pendingGroup = collect($this->getJson('/api/v1/admin/notifications')->json('data.groups'))
            ->firstWhere('type', 'pending_product');

        $this->assertNotNull($pendingGroup);
        $this->assertGreaterThanOrEqual(count($pendingGroup['items']), $pendingGroup['total_count']);
        $this->assertSame($pendingGroup['unread_count'], count($pendingGroup['items']));
        foreach ($pendingGroup['items'] as $item) {
            $this->assertFalse($item['is_read']);
        }
    }

    public function test_mark_read_removes_item_from_dropdown_list(): void
    {
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
        $this->assertSame($before - 1, $after);

        $response = $this->getJson('/api/v1/admin/notifications')->assertOk();
        $keys = collect($response->json('data.groups'))
            ->flatMap(fn (array $group): array => $group['items'])
            ->pluck('key');

        $this->assertFalse($keys->contains($key));
    }

    public function test_shared_read_state_hides_item_for_all_admins(): void
    {
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
        $keys = collect($this->getJson('/api/v1/admin/notifications')->json('data.groups'))
            ->flatMap(fn (array $group): array => $group['items'])
            ->pluck('key');

        $this->assertFalse($keys->contains($key));
        $this->assertSame(1, AdminNotificationRead::query()->where('notification_key', $key)->count());
    }

    public function test_limited_admin_omits_gated_notification_types(): void
    {
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

        $this->assertTrue($types->contains('pending_product'));
        $this->assertFalse($types->contains('abuse_report'));
    }

    public function test_mark_read_returns_not_found_for_invalid_key(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/notifications/not-a-valid-key/read')
            ->assertNotFound();
    }

    public function test_unread_count_endpoint_returns_count(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $listCount = $this->getJson('/api/v1/admin/notifications')->json('data.unread_count');
        $badgeCount = $this->getJson('/api/v1/admin/notifications/unread-count')
            ->assertOk()
            ->json('data.count');

        $this->assertSame($listCount, $badgeCount);
    }

    public function test_mark_all_read_marks_visible_notifications(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $before = $this->getJson('/api/v1/admin/notifications')->json('data.unread_count');
        $this->assertGreaterThan(0, $before);

        $this->postJson('/api/v1/admin/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.groups', []);
    }
}
