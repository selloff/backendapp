<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Payment\Services\MembershipAutoBumpService;
use Illuminate\Console\Command;

class RunMembershipAutoBumpCommand extends Command
{
    protected $signature = 'selloff:membership-auto-bump';

    protected $description = 'Refresh listing bump timestamps for vendors with auto-bump membership entitlements';

    public function handle(MembershipAutoBumpService $service): int
    {
        $result = $service->run();

        $this->info(sprintf(
            'Processed %d vendor subscription(s); bumped %d listing(s).',
            $result['vendors_processed'],
            $result['products_bumped'],
        ));

        return self::SUCCESS;
    }
}
