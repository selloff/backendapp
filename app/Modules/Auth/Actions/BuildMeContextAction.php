<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;

class BuildMeContextAction
{
    public function execute(User $user): User
    {
        return $user->load(['roles.permissions', 'permissions', 'referralProfile']);
    }
}
