<?php

namespace Tests\Unit\LegacyImport;

use App\LegacyImport\Support\LegacyRolePermissionMapper;
use Tests\TestCase;

class LegacyRolePermissionMapperTest extends TestCase
{
    public function test_slugs_from_legacy_csv_maps_one_based_indexes(): void
    {
        $slugs = LegacyRolePermissionMapper::slugsFromLegacyCsv('1,2,18');

        $this->assertSame(['admin_panel', 'vendor', 'membership'], $slugs);
    }

    public function test_slugs_from_all_returns_full_legacy_permission_list(): void
    {
        $slugs = LegacyRolePermissionMapper::slugsFromLegacyCsv('all');

        $this->assertSame(config('selloff.legacy_role_permissions'), $slugs);
    }

    public function test_spatie_role_name_maps_known_legacy_ids(): void
    {
        $row = ['role_name' => 'a:1:{i:0;a:2:{s:7:"lang_id";s:1:"1";s:4:"name";s:5:"Admin";}}'];

        $this->assertSame('super-admin', LegacyRolePermissionMapper::spatieRoleName(1, $row));
        $this->assertSame('vendor', LegacyRolePermissionMapper::spatieRoleName(2, $row));
        $this->assertSame('member', LegacyRolePermissionMapper::spatieRoleName(3, $row));
        $this->assertSame('admin', LegacyRolePermissionMapper::spatieRoleName(5, $row));
    }

    public function test_spatie_role_name_slugifies_custom_roles(): void
    {
        $row = ['role_name' => 'a:1:{i:0;a:2:{s:7:"lang_id";s:1:"1";s:4:"name";s:16:"Customer Success";}}'];

        $this->assertSame('customer-success', LegacyRolePermissionMapper::spatieRoleName(4, $row));
    }
}
