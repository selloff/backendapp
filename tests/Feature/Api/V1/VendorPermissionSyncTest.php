<?php

use App\Models\User;
use App\Services\Auth\RolePermissionSync;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('vendor role without permissions is repaired by sync', function () {
    Role::findOrCreate('vendor', 'web')->syncPermissions([]);

    $vendor = User::factory()->create();
    $vendor->syncRoles(['vendor']);

    expect($vendor->can('vendor'))->toBeFalse();

    app(RolePermissionSync::class)->sync();

    $vendor->refresh();
    expect($vendor->can('vendor'))->toBeTrue();
});

test('vendor with synced permissions can access vendor orders', function () {
    app(RolePermissionSync::class)->sync();

    $vendor = User::factory()->create();
    $vendor->syncRoles(['vendor']);

    $this->actingAs($vendor, 'sanctum')
        ->getJson('/api/v1/vendor/orders')
        ->assertOk();
});