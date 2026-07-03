<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Payment\Services\MembershipExpiryService;
use Illuminate\Console\Command;

class DeactivateExpiredMembershipsCommand extends Command
{
    protected $signature = 'selloff:deactivate-expired-memberships';

    protected $description = 'Deactivate vendor membership subscriptions after the grace period has elapsed';

    public function handle(MembershipExpiryService $service): int
    {
        $deactivated = $service->deactivateExpiredSubscriptions();

        $this->info("Deactivated {$deactivated} expired membership subscription(s).");

        return self::SUCCESS;
    }
}
