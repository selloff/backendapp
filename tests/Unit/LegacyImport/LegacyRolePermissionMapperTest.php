<?php

use App\LegacyImport\Support\LegacyRolePermissionMapper;

test('slugs from legacy csv maps one based indexes', function () {
    $slugs = LegacyRolePermissionMapper::slugsFromLegacyCsv('1,2,18');

    expect($slugs)->toBe(['admin_panel', 'vendor', 'membership']);
});

test('slugs from all returns full legacy permission list', function () {
    $slugs = LegacyRolePermissionMapper::slugsFromLegacyCsv('all');

    expect($slugs)->toBe(config('selloff.legacy_role_permissions'));
});

test('spatie role name maps known legacy ids', function () {
    $row = ['role_name' => 'a:1:{i:0;a:2:{s:7:"lang_id";s:1:"1";s:4:"name";s:5:"Admin";}}'];

    expect(LegacyRolePermissionMapper::spatieRoleName(1, $row))->toBe('super-admin');
    expect(LegacyRolePermissionMapper::spatieRoleName(2, $row))->toBe('vendor');
    expect(LegacyRolePermissionMapper::spatieRoleName(3, $row))->toBe('member');
    expect(LegacyRolePermissionMapper::spatieRoleName(5, $row))->toBe('admin');
});

test('spatie role name slugifies custom roles', function () {
    $row = ['role_name' => 'a:1:{i:0;a:2:{s:7:"lang_id";s:1:"1";s:4:"name";s:16:"Customer Success";}}'];

    expect(LegacyRolePermissionMapper::spatieRoleName(4, $row))->toBe('customer-success');
});
