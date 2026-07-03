<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\LegacyImportMapRepository;
use App\LegacyImport\MySqlDumpReader;
use App\LegacyImport\Support\LegacyRolePermissionMapper;
use App\Services\Auth\RolePermissionSync;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsLegacyImporter implements LegacyImporter
{
    /** @var list<string> */
    private const SYSTEM_ROLES = ['super-admin', 'admin', 'vendor', 'member'];

    public function __construct(
        private readonly LegacyImportMapRepository $maps,
    ) {}

    public function legacyTable(): string
    {
        return 'roles_permissions';
    }

    public function import(LegacyImportContext $context, MySqlDumpReader $reader): void
    {
        if (! $context->shouldImportTable($this->legacyTable()) || ! $reader->hasTable('roles_permissions')) {
            return;
        }

        app(RolePermissionSync::class)->sync();

        foreach ($reader->rows('roles_permissions') as $row) {
            $context->notePlanned($this->legacyTable());

            $legacyId = (int) ($row['id'] ?? 0);
            if ($legacyId <= 0) {
                $context->noteSkipped($this->legacyTable());

                continue;
            }

            $roleName = LegacyRolePermissionMapper::spatieRoleName($legacyId, $row);
            $permissionSlugs = LegacyRolePermissionMapper::slugsFromLegacyCsv($row['permissions'] ?? null);

            if ($context->dryRun) {
                $existingRoleId = Role::query()
                    ->where('guard_name', 'web')
                    ->where('name', $roleName)
                    ->value('id');

                $this->maps->remember(
                    $context,
                    'roles_permissions',
                    $legacyId,
                    'roles',
                    (int) ($existingRoleId ?? $legacyId),
                );
                $context->noteImported($this->legacyTable());

                continue;
            }

            $role = $this->resolveRole($legacyId, $row, $roleName);
            $this->syncRolePermissions($role, $permissionSlugs, $row);

            $this->maps->remember($context, 'roles_permissions', $legacyId, 'roles', $role->id);
            $context->noteImported($this->legacyTable());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveRole(int $legacyId, array $row, string $roleName): Role
    {
        if (in_array($roleName, self::SYSTEM_ROLES, true)) {
            return Role::findByName($roleName, 'web');
        }

        return Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web'],
        );
    }

    /**
     * @param  list<string>  $permissionSlugs
     * @param  array<string, mixed>  $row
     */
    private function syncRolePermissions(Role $role, array $permissionSlugs, array $row): void
    {
        if ($role->name === 'super-admin' || strtolower(trim((string) ($row['permissions'] ?? ''))) === 'all') {
            $role->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

            return;
        }

        $role->syncPermissions($permissionSlugs);
    }
}
