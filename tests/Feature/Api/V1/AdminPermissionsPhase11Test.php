<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPermissionsPhase11Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_legacy_permission_catalog_has_thirty_six_slugs(): void
    {
        $permissions = config('selloff.legacy_role_permissions', []);

        $this->assertCount(36, $permissions);
        $this->assertContains('admin_panel', $permissions);
        $this->assertContains('ai_writer', $permissions);
        $this->assertNotContains('storage', $permissions);
    }

    public function test_roles_create_meta_returns_legacy_permission_slugs(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $legacy = config('selloff.legacy_role_permissions', []);

        $this->getJson('/api/v1/roles/create-meta')
            ->assertOk()
            ->assertJsonCount(count($legacy), 'data.permissions')
            ->assertJsonPath('data.permissions.0', 'admin_panel')
            ->assertJsonPath('data.permissions.35', 'ai_writer');
    }

    public function test_superadmin_me_includes_all_legacy_permissions(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $legacy = config('selloff.legacy_role_permissions', []);

        $response = $this->getJson('/api/v1/auth/me')->assertOk();
        $permissions = $response->json('data.permissions');

        foreach ($legacy as $slug) {
            $this->assertContains($slug, $permissions, "Missing permission: {$slug}");
        }
    }

    public function test_limited_admin_role_exposes_only_assigned_permissions(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'theme-only', 'guard_name' => 'web']);
        $role->syncPermissions(['admin_panel', 'theme']);

        $user = User::query()->create([
            'first_name' => 'Theme',
            'last_name' => 'Editor',
            'slug' => 'theme-editor',
            'email' => 'theme-editor@selloff.test',
            'password' => Hash::make('password'),
            'is_enable_login' => true,
            'is_disable' => false,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.permissions', ['admin_panel', 'theme']);
    }

    public function test_custom_role_can_be_created_and_updated_with_permissions(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/v1/roles', [
            'name' => 'content-editor',
            'permissions' => ['admin_panel', 'blog', 'comments'],
        ])->assertCreated();

        $roleId = $create->json('data.id');

        $this->getJson("/api/v1/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('data.permissions', ['admin_panel', 'blog', 'comments']);

        $this->putJson("/api/v1/roles/{$roleId}", [
            'permissions' => ['admin_panel', 'blog', 'comments', 'pages'],
        ])
            ->assertOk();

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'content-editor', 'permissions' => ['admin_panel', 'blog', 'comments', 'pages']]);
    }

    public function test_admin_with_only_admin_panel_cannot_access_orders_api(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'marketing-only', 'guard_name' => 'web']);
        $role->syncPermissions(['admin_panel', 'ai_writer']);

        $user = User::query()->create([
            'first_name' => 'Marketing',
            'last_name' => 'Only',
            'slug' => 'marketing-only',
            'email' => 'marketing-only@selloff.test',
            'password' => Hash::make('password'),
            'is_enable_login' => true,
            'is_disable' => false,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/orders')->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard')->assertOk();
    }
}
