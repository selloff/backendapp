<?php

namespace App\Modules\Selloff\User\Services;

use App\Models\User;
use App\Modules\Selloff\User\Models\LoginActivity;

class RecordLoginActivityService
{
    public function record(User $user, ?string $ipAddress = null, ?string $userAgent = null): LoginActivity
    {
        return LoginActivity::query()->create([
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_at' => now(),
        ]);
    }
}
