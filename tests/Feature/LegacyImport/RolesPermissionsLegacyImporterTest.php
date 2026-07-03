<?php

namespace Tests\Feature\LegacyImport;

use App\LegacyImport\Importers\RolesPermissionsLegacyImporter;
use App\LegacyImport\Importers\UsersLegacyImporter;
use App\LegacyImport\LegacyImportContext;
use App\LegacyImport\MySqlDumpReader;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsLegacyImporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_imports_legacy_role_permissions_into_spatie_roles(): void
    {
        $dumpPath = storage_path('app/test-roles-permissions-import.sql');
        file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `roles_permissions` (
  `id` int NOT NULL,
  `role_name` text,
  `permissions` text,
  `is_super_admin` tinyint(1) DEFAULT '0',
  `is_default` tinyint(1) DEFAULT '0',
  `is_admin` tinyint(1) DEFAULT '0',
  `is_vendor` tinyint(1) DEFAULT '0',
  `is_member` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `roles_permissions` (`id`, `role_name`, `permissions`, `is_super_admin`, `is_default`, `is_admin`, `is_vendor`, `is_member`)
VALUES
(1,'a:1:{i:0;a:2:{s:7:"lang_id";s:1:"1";s:4:"name";s:11:"Super Admin";}}','all',1,1,1,0,0),
(2,'a:1:{i:0;a:2:{s:7:"lang_id";s:1:"1";s:4:"name";s:6:"Vendor";}}','2',0,1,0,1,0),
(4,'a:1:{i:0;a:2:{s:7:"lang_id";s:1:"1";s:4:"name";s:16:"Customer Success";}}','1,2,18',0,0,1,1,0);
SQL);

        $context = new LegacyImportContext(dryRun: false, tableFilter: 'roles_permissions');
        app(RolesPermissionsLegacyImporter::class)->import($context, new MySqlDumpReader($dumpPath));

        $vendor = Role::findByName('vendor', 'web');
        $this->assertSame(['vendor'], $vendor->permissions->pluck('name')->all());

        $customerSuccess = Role::findByName('customer-success', 'web');
        $this->assertNotNull($customerSuccess);
        $this->assertEqualsCanonicalizing(
            ['admin_panel', 'vendor', 'membership'],
            $customerSuccess->permissions->pluck('name')->all(),
        );

        @unlink($dumpPath);
    }

    public function test_users_importer_assigns_role_from_roles_permissions_map(): void
    {
        $dumpPath = storage_path('app/test-users-role-map-import.sql');
        file_put_contents($dumpPath, <<<'SQL'
CREATE TABLE `roles_permissions` (
  `id` int NOT NULL,
  `role_name` text,
  `permissions` text,
  `is_super_admin` tinyint(1) DEFAULT '0',
  `is_default` tinyint(1) DEFAULT '0',
  `is_admin` tinyint(1) DEFAULT '0',
  `is_vendor` tinyint(1) DEFAULT '0',
  `is_member` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

INSERT INTO `roles_permissions` (`id`, `role_name`, `permissions`, `is_super_admin`, `is_default`, `is_admin`, `is_vendor`, `is_member`)
VALUES
(4,'a:1:{i:0;a:2:{s:7:"lang_id";s:1:"1";s:4:"name";s:16:"Customer Success";}}','1,2,18',0,0,1,1,0);

CREATE TABLE `users` (
  `id` int NOT NULL,
  `role_id` int DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `role_id`, `username`, `slug`, `email`, `password`, `created_at`, `updated_at`)
VALUES
(9001,4,'cs-agent','cs-agent','cs-agent@example.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','2024-01-01 00:00:00','2024-01-01 00:00:00');
SQL);

        $reader = new MySqlDumpReader($dumpPath);
        $context = new LegacyImportContext(dryRun: false);

        app(RolesPermissionsLegacyImporter::class)->import($context, $reader);
        app(UsersLegacyImporter::class)->import($context, $reader);

        $user = DB::table('users')->where('id', 9001)->first();
        $this->assertNotNull($user);

        $assignedRole = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', 9001)
            ->value('roles.name');

        $this->assertSame('customer-success', $assignedRole);

        @unlink($dumpPath);
    }
}
