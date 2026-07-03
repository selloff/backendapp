<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Modules\Auth\Actions\LoginUserAction;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipActivationService;
use App\Modules\Selloff\User\Models\VendorProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AdminUserManagementService
{
    public function confirmEmail(User $user): User
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user->fresh();
    }

    public function toggleBan(User $user, User $actor): User
    {
        $this->guardSuperAdminTarget($user, $actor);

        $user->update(['is_banned' => ! $user->is_banned]);

        return $user->fresh();
    }

    public function toggleAffiliate(User $user, User $actor): User
    {
        $this->guardSuperAdminTarget($user, $actor);

        $current = (int) ($user->is_affiliate ?? 0);
        $user->update(['is_affiliate' => $current === 1 ? 2 : 1]);

        return $user->fresh();
    }

    public function changeRole(User $user, string $roleName, User $actor): User
    {
        $this->guardSuperAdminTarget($user, $actor);

        abort_unless(
            Role::query()->where('guard_name', 'web')->where('name', $roleName)->exists(),
            422,
            'Invalid role.',
        );

        $user->syncRoles([$roleName]);

        return $user->fresh()->load('roles');
    }

    public function assignMembershipPlan(User $user, int $planId, MembershipActivationService $activation): User
    {
        abort_unless($user->hasRole('vendor'), 422, 'Membership plans can only be assigned to vendors.');

        $plan = MembershipPlan::query()->findOrFail($planId);
        $months = max(1, (int) ceil(((int) ($plan->duration_days ?? 30)) / 30));

        $activation->activate(
            $user,
            $plan,
            'new',
            $months,
            0,
        );

        return $user->fresh();
    }

    public function updateProfile(User $user, array $data, User $actor): User
    {
        $this->guardSuperAdminTarget($user, $actor);

        $socialMediaData = $data['social_media_data'] ?? null;
        $commissionMode = $data['commission_mode'] ?? null;
        $commissionRate = $data['commission_rate'] ?? null;
        unset($data['social_media_data'], $data['commission_mode'], $data['commission_rate'], $data['password_confirmation']);

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
            unset($data['roles']);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill($data);
        $user->save();

        if ($user->hasRole('vendor')) {
            $profileData = [];

            if ($socialMediaData !== null) {
                $profileData['social_media_data'] = $this->normalizeSocialMediaData($socialMediaData);
            }

            if ($commissionMode !== null) {
                $profileData = [
                    ...$profileData,
                    ...$this->commissionValuesFromMode($commissionMode, $commissionRate),
                ];
            }

            if ($profileData !== []) {
                VendorProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData,
                );
            }
        } elseif ($socialMediaData !== null) {
            $user->update([
                'social_media_data' => $this->normalizeSocialMediaData($socialMediaData),
            ]);
        }

        return $user->fresh()->load(['roles', 'vendorProfile']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function commissionValuesFromMode(string $mode, mixed $rate): array
    {
        return match ($mode) {
            'custom' => [
                'is_commission_set' => true,
                'commission_rate' => is_numeric($rate) && (float) $rate >= 0 ? (float) $rate : 0,
            ],
            'none' => [
                'is_commission_set' => true,
                'commission_rate' => 0,
            ],
            default => [
                'is_commission_set' => false,
                'commission_rate' => 0,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function normalizeSocialMediaData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            $normalized[$key] = $this->ensureHttpsUrl($trimmed);
        }

        return $normalized;
    }

    private function ensureHttpsUrl(string $url): string
    {
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        return 'https://'.$url;
    }

    /**
     * @return array{user: User, token: string}
     */
    public function impersonate(
        User $target,
        User $admin,
        LoginUserAction $login,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        abort_unless($admin->can('membership') || $admin->hasRole('super-admin'), 403);

        $this->guardSuperAdminTarget($target, $admin);

        if ($target->is_banned || $target->is_disable || ! $target->is_enable_login) {
            throw ValidationException::withMessages([
                'user' => ['This account cannot be used for login.'],
            ]);
        }

        return $login->issueToken($target, 'spa-impersonation', $ipAddress, $userAgent);
    }

    public function guardSuperAdminTarget(User $target, User $actor): void
    {
        if ($target->hasRole('super-admin') && ! $actor->hasRole('super-admin')) {
            abort(422, 'You cannot modify a super-admin account.');
        }
    }
}
