<?php

namespace App\Services\Auth;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSync
{
    /**
     * Ensure core roles exist and carry the permissions expected by the API middleware.
     */
    public function sync(): void
    {
        foreach (['super-admin', 'admin', 'vendor', 'member'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $permissions = config('selloff.legacy_role_permissions', [
            'admin_panel',
            'vendor',
            'orders',
            'products',
            'payment_settings',
            'membership',
        ]);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $adminPermissions = array_values(array_filter(
            $permissions,
            fn (string $permission) => $permission !== 'vendor',
        ));

        Role::findByName('admin', 'web')?->syncPermissions($adminPermissions);
        Role::findByName('vendor', 'web')?->syncPermissions(['vendor', 'products', 'orders']);
        Role::findByName('member', 'web')?->syncPermissions([]);
        Role::findByName('super-admin', 'web')?->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
