<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Selloff\Admin\Support\AdminPinContext;

trait InteractsWithAdminPin
{
    /**
     * @return array<string, string>
     */
    protected function superAdminPinHeaders(string $pin = '196001'): array
    {
        return superAdminPinHeaders($pin);
    }

    /**
     * @return array<string, string>
     */
    protected function adminPinHeaders(string $pin = '196001'): array
    {
        return adminPinHeaders($pin);
    }

    protected function asVerifiedSuperAdmin(): User
    {
        return verifiedSuperAdmin();
    }
}
