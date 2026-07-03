<?php

namespace Tests\Concerns;

use App\Modules\Selloff\Admin\Support\AdminPinContext;

trait InteractsWithAdminPin
{
    /**
     * @return array<string, string>
     */
    protected function superAdminPinHeaders(string $pin = '196001'): array
    {
        return [AdminPinContext::HEADER_SUPER_ADMIN_PIN => $pin];
    }
}
