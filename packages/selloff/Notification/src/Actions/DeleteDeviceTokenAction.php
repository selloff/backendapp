<?php

namespace App\Modules\Selloff\Notification\Actions;

use App\Models\User;
use App\Modules\Selloff\Notification\Models\DeviceToken;

class DeleteDeviceTokenAction
{
    public function execute(User $user, string $token): bool
    {
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        return DeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token', $token)
            ->delete() > 0;
    }
}
