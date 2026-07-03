<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Support\Services\VendorFeedbackRatingService;
use Illuminate\Console\Command;

class BackfillVendorFeedbackStatsCommand extends Command
{
    protected $signature = 'selloff:backfill-vendor-feedback-stats
                            {--vendor-id= : Recompute stats for a single vendor user id}';

    protected $description = 'Recompute denormalized vendor feedback stats on vendor_profiles';

    public function handle(VendorFeedbackRatingService $ratings): int
    {
        $vendorId = $this->option('vendor-id');

        if ($vendorId !== null && $vendorId !== '') {
            $ratings->recomputeForVendor((int) $vendorId);
            $this->info("Recomputed feedback stats for vendor #{$vendorId}.");

            return self::SUCCESS;
        }

        $count = $ratings->recomputeAll();
        $this->info("Recomputed feedback stats for {$count} vendor profile(s).");

        return self::SUCCESS;
    }
}
