<?php

namespace App\Console\Commands;

use App\Services\Auth\RolePermissionSync;
use Illuminate\Console\Command;

class SyncRolePermissionsCommand extends Command
{
    protected $signature = 'selloff:sync-role-permissions';

    protected $description = 'Sync Spatie role permissions for admin, vendor, and member roles';

    public function handle(RolePermissionSync $sync): int
    {
        $sync->sync();
        $this->info('Role permissions synced.');

        return self::SUCCESS;
    }
}
