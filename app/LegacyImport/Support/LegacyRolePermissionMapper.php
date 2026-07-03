<?php

namespace App\LegacyImport\Support;

use Illuminate\Support\Str;

class LegacyRolePermissionMapper
{
    /**
     * @return list<string>
     */
    public static function slugsFromLegacyCsv(?string $permissions): array
    {
        if ($permissions === null || trim($permissions) === '') {
            return [];
        }

        if (strtolower(trim($permissions)) === 'all') {
            return config('selloff.legacy_role_permissions', []);
        }

        $indexList = config('selloff.legacy_role_permissions', []);
        $slugs = [];

        foreach (explode(',', $permissions) as $part) {
            $index = (int) trim($part);
            if ($index <= 0) {
                continue;
            }

            $slug = $indexList[$index - 1] ?? null;
            if (is_string($slug) && $slug !== '') {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function spatieRoleName(int $legacyId, array $row): string
    {
        return match ($legacyId) {
            1 => 'super-admin',
            2 => 'vendor',
            3 => 'member',
            5 => 'admin',
            default => self::customRoleName($row, $legacyId),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function roleDisplayName(array $row, int $legacyId): string
    {
        return LegacyValueCoercer::localizedLabel(
            $row['role_name'] ?? null,
            'Legacy Role '.$legacyId,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function customRoleName(array $row, int $legacyId): string
    {
        $slug = Str::slug(self::roleDisplayName($row, $legacyId));

        if ($slug === '') {
            return 'legacy-role-'.$legacyId;
        }

        if (in_array($slug, ['super-admin', 'admin', 'vendor', 'member'], true)) {
            return 'legacy-role-'.$legacyId;
        }

        return $slug;
    }
}
