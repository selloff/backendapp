<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Payment\Services\MembershipTopBoostExpiryService;
use Illuminate\Console\Command;

class ExpireMembershipTopBoostsCommand extends Command
{
    protected $signature = 'selloff:expire-membership-top-boosts';

    protected $description = 'Deactivate membership TOP boosts after their expiry timestamp';

    public function handle(MembershipTopBoostExpiryService $service): int
    {
        $expired = $service->deactivateExpiredBoosts();

        $this->info("Expired {$expired} membership TOP boost(s).");

        return self::SUCCESS;
    }
}
