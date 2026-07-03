<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Selloff\User\Services\RecordLoginActivityService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function __construct(
        private readonly RecordLoginActivityService $loginActivity,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    public function execute(
        string $email,
        string $password,
        string $deviceName = 'spa',
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_enable_login || $user->is_disable) {
            throw ValidationException::withMessages([
                'email' => ['This account is disabled.'],
            ]);
        }

        $user->loadMissing(['roles.permissions', 'permissions']);

        $abilities = \App\Modules\Selloff\Admin\Support\AdminPinContext::loginAbilities($user);
        $token = $user->createToken($deviceName, $abilities)->plainTextToken;
        $this->loginActivity->record($user, $ipAddress, $userAgent);

        return ['user' => $user, 'token' => $token];
    }

    /**
     * @return array{user: User, token: string}
     */
    public function issueToken(
        User $user,
        string $deviceName = 'spa',
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        if (! $user->is_enable_login || $user->is_disable) {
            throw ValidationException::withMessages([
                'email' => ['This account is disabled.'],
            ]);
        }

        $user->loadMissing(['roles.permissions', 'permissions']);

        $abilities = \App\Modules\Selloff\Admin\Support\AdminPinContext::loginAbilities($user);
        $token = $user->createToken($deviceName, $abilities)->plainTextToken;
        $this->loginActivity->record($user, $ipAddress, $userAgent);

        return ['user' => $user, 'token' => $token];
    }
}
