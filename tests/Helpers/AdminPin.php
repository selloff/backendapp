<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Support\AdminPinContext;

/**
 * @return array<string, string>
 */
function adminPinHeaders(string $pin = '196001'): array
{
    return [AdminPinContext::HEADER_ADMIN_PIN => $pin];
}

/**
 * @return array<string, string>
 */
function superAdminPinHeaders(string $pin = '196001'): array
{
    return [AdminPinContext::HEADER_SUPER_ADMIN_PIN => $pin];
}

/**
 * Admin DELETE on settings-managed resources (languages, tax rules, etc.).
 *
 * @return array<string, string>
 */
function adminSettingsDeleteHeaders(string $pin = '196001'): array
{
    return array_merge(adminPinHeaders($pin), superAdminPinHeaders($pin));
}

function verifiedSuperAdmin(string $pin = '196001'): User
{
    $user = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $token = $user->createToken('test', [AdminPinContext::ABILITY_VERIFIED]);
    test()->withToken($token->plainTextToken);

    return $user;
}
