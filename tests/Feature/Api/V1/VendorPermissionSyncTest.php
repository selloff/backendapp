<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\Auth\RolePermissionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorPermissionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_role_without_permissions_is_repaired_by_sync(): void
    {
        Role::findOrCreate('vendor', 'web')->syncPermissions([]);

        $vendor = User::factory()->create();
        $vendor->syncRoles(['vendor']);

        $this->assertFalse($vendor->can('vendor'));

        app(RolePermissionSync::class)->sync();

        $vendor->refresh();
        $this->assertTrue($vendor->can('vendor'));
    }

    public function test_vendor_with_synced_permissions_can_access_vendor_orders(): void
    {
        app(RolePermissionSync::class)->sync();

        $vendor = User::factory()->create();
        $vendor->syncRoles(['vendor']);

        $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/v1/vendor/orders')
            ->assertOk();
    }
}
