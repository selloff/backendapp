<?php

namespace App\Modules\Selloff\Admin\Services;

use App\Models\User;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminPinService
{
    private const MAX_FAILURES = 5;

    private const LOCKOUT_SECONDS = 900;

    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly SuperAdminPinBootstrap $superAdminPinBootstrap,
    ) {}

    public function verifyLoginPin(User $user, string $pin): void
    {
        $this->assertNotLocked($user, 'login');

        if (AdminPinContext::isSuperAdmin($user)) {
            if (! $this->superAdminPinBootstrap->isConfigured()) {
                throw ValidationException::withMessages([
                    'pin' => ['Super Admin PIN is not configured. Run: php artisan selloff:bootstrap-super-admin-pin'],
                ]);
            }

            if ($this->checkSuperAdminPin($pin)) {
                $this->clearFailures($user, 'login');

                return;
            }

            $this->recordFailure($user, 'login');
            throw ValidationException::withMessages(['pin' => ['Invalid PIN.']]);
        }

        if ($user->admin_pin_revoked_at !== null || $user->admin_pin_hash === null) {
            throw ValidationException::withMessages([
                'pin' => ['Admin PIN is not configured. Contact a super admin.'],
            ]);
        }

        if (Hash::check($pin, $user->admin_pin_hash)) {
            $this->clearFailures($user, 'login');

            return;
        }

        $this->recordFailure($user, 'login');
        throw ValidationException::withMessages(['pin' => ['Invalid PIN.']]);
    }

    public function verifyDeletePin(User $user, string $pin): void
    {
        $this->assertNotLocked($user, 'delete');

        if (AdminPinContext::isSuperAdmin($user)) {
            if ($this->checkSuperAdminPin($pin) || $this->checkAdminPin($user, $pin)) {
                $this->clearFailures($user, 'delete');

                return;
            }

            $this->recordFailure($user, 'delete');
            throw ValidationException::withMessages(['pin' => ['Invalid PIN.']]);
        }

        if ($this->checkAdminPin($user, $pin)) {
            $this->clearFailures($user, 'delete');

            return;
        }

        $this->recordFailure($user, 'delete');
        throw ValidationException::withMessages(['pin' => ['Invalid PIN.']]);
    }

    public function verifySuperAdminPin(string $pin): void
    {
        $this->assertNotLockedOut('super-settings');

        if (! $this->superAdminPinBootstrap->isConfigured()) {
            throw ValidationException::withMessages([
                'pin' => ['Super Admin PIN is not configured. Run: php artisan selloff:bootstrap-super-admin-pin'],
            ]);
        }

        if ($this->checkSuperAdminPin($pin)) {
            $this->clearLockedOut('super-settings');

            return;
        }

        $this->recordLockedOutFailure('super-settings');
        throw ValidationException::withMessages(['pin' => ['Invalid PIN.']]);
    }

    public function setAdminPin(User $target, string $pin): void
    {
        $target->forceFill([
            'admin_pin_hash' => Hash::make($pin),
            'admin_pin_set_at' => now(),
            'admin_pin_revoked_at' => null,
        ])->save();
    }

    public function revokeAdminPin(User $target): void
    {
        $target->forceFill([
            'admin_pin_hash' => null,
            'admin_pin_revoked_at' => now(),
        ])->save();
    }

    public function rotateSuperAdminPin(string $pin): void
    {
        $this->settings->upsertMany([
            'super_admin_pin_hash' => Hash::make($pin),
        ], 'security');
    }

    private function checkSuperAdminPin(string $pin): bool
    {
        $hash = $this->settings->all()['super_admin_pin_hash'] ?? null;

        return is_string($hash) && $hash !== '' && Hash::check($pin, $hash);
    }

    private function checkAdminPin(User $user, string $pin): bool
    {
        if ($user->admin_pin_hash === null || $user->admin_pin_revoked_at !== null) {
            return false;
        }

        return Hash::check($pin, $user->admin_pin_hash);
    }

    private function assertNotLocked(User $user, string $action): void
    {
        $failures = (int) Cache::get($this->failureKey($user->id, $action), 0);

        if ($failures >= self::MAX_FAILURES) {
            throw ValidationException::withMessages([
                'pin' => ['Too many failed attempts. Try again later.'],
            ]);
        }
    }

    private function recordFailure(User $user, string $action): void
    {
        $key = $this->failureKey($user->id, $action);
        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, self::LOCKOUT_SECONDS);
    }

    private function clearFailures(User $user, string $action): void
    {
        Cache::forget($this->failureKey($user->id, $action));
    }

    private function assertNotLockedOut(string $scope): void
    {
        $failures = (int) Cache::get($this->scopedFailureKey($scope), 0);

        if ($failures >= self::MAX_FAILURES) {
            throw ValidationException::withMessages([
                'pin' => ['Too many failed attempts. Try again later.'],
            ]);
        }
    }

    private function recordLockedOutFailure(string $scope): void
    {
        $key = $this->scopedFailureKey($scope);
        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, self::LOCKOUT_SECONDS);
    }

    private function clearLockedOut(string $scope): void
    {
        Cache::forget($this->scopedFailureKey($scope));
    }

    private function failureKey(int $userId, string $action): string
    {
        return "admin_pin_failures:{$userId}:{$action}";
    }

    private function scopedFailureKey(string $scope): string
    {
        return "admin_pin_failures:scope:{$scope}";
    }
}
