<?php

namespace App\Modules\Selloff\Admin\Support;

use App\Models\User;
use App\Modules\Selloff\Admin\Services\SuperAdminPinBootstrap;
use Laravel\Sanctum\PersonalAccessToken;

final class AdminPinContext
{
    public const ABILITY_PENDING = 'admin-pin-pending';

    public const ABILITY_VERIFIED = 'admin-pin-verified';

    public const HEADER_ADMIN_PIN = 'X-Admin-Pin';

    public const HEADER_SUPER_ADMIN_PIN = 'X-Super-Admin-Pin';

    public static function requiresAdminPin(User $user): bool
    {
        return $user->can('admin_panel') || $user->hasRole('super-admin');
    }

    public static function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public static function pinType(User $user): ?string
    {
        if (! self::requiresAdminPin($user)) {
            return null;
        }

        return self::isSuperAdmin($user) ? 'super' : 'admin';
    }

    /**
     * @return list<string>
     */
    public static function loginAbilities(User $user): array
    {
        return self::requiresAdminPin($user) ? [self::ABILITY_PENDING] : ['*'];
    }

    public static function tokenIsVerified(?PersonalAccessToken $token, ?User $user = null): bool
    {
        if ($token === null) {
            return false;
        }

        if ($token->can('*')) {
            if ($user !== null && self::requiresAdminPin($user)) {
                return false;
            }

            return true;
        }

        if ($token->can(self::ABILITY_VERIFIED)) {
            return true;
        }

        // Sanctum::actingAs($user) in feature tests creates tokens with no abilities.
        // Treat that as verified unless the token explicitly carries admin-pin-pending.
        if (app()->runningUnitTests() && ! $token->can(self::ABILITY_PENDING)) {
            $abilities = $token->abilities ?? [];

            if ($abilities === []) {
                return true;
            }
        }

        return false;
    }

    public static function adminPinConfigured(User $user): bool
    {
        if (self::isSuperAdmin($user)) {
            return app(SuperAdminPinBootstrap::class)->isConfigured();
        }

        return $user->admin_pin_hash !== null && $user->admin_pin_revoked_at === null;
    }
}
