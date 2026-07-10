<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('legacy permission catalog has thirty six slugs', function () {
    $permissions = config('selloff.legacy_role_permissions', []);

    expect($permissions)->toHaveCount(36);
    expect($permissions)->toContain('admin_panel');
    expect($permissions)->toContain('ai_writer');
    expect($permissions)->not->toContain('storage');
});

test('roles create meta returns legacy permission slugs', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $legacy = config('selloff.legacy_role_permissions', []);

    $this->getJson('/api/v1/roles/create-meta')
        ->assertOk()
        ->assertJsonCount(count($legacy), 'data.permissions')
        ->assertJsonPath('data.permissions.0', 'admin_panel')
        ->assertJsonPath('data.permissions.35', 'ai_writer');
});

test('superadmin me includes all legacy permissions', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $legacy = config('selloff.legacy_role_permissions', []);

    $response = $this->getJson('/api/v1/auth/me')->assertOk();
    $permissions = $response->json('data.permissions');

    foreach ($legacy as $slug) {
        expect($permissions)->toContain($slug);
    }
});

test('limited admin role exposes only assigned permissions', function () {
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
});

test('custom role can be created and updated with permissions', function () {
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
});

test('admin with only admin panel cannot access orders api', function () {
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
});
