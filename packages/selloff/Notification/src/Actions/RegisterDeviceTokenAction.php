<?php

namespace App\Modules\Selloff\Notification\Actions;

use App\Models\User;
use App\Modules\Selloff\Notification\Models\DeviceToken;
use Illuminate\Validation\ValidationException;

class RegisterDeviceTokenAction
{
    /**
     * @param  array{token: string, platform: string, device_id?: string|null}  $payload
     */
    public function execute(User $user, array $payload): DeviceToken
    {
        $token = trim($payload['token']);
        $platform = strtolower(trim($payload['platform']));

        if ($token === '') {
            throw ValidationException::withMessages([
                'token' => ['Device token is required.'],
            ]);
        }

        if (! in_array($platform, ['android', 'ios'], true)) {
            throw ValidationException::withMessages([
                'platform' => ['Platform must be android or ios.'],
            ]);
        }

        return DeviceToken::query()->updateOrCreate(
            ['token' => $token],
            [
                'user_id' => $user->id,
                'platform' => $platform,
                'device_id' => filled($payload['device_id'] ?? null)
                    ? trim((string) $payload['device_id'])
                    : null,
                'last_used_at' => now(),
            ],
        );
    }
}
