<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Escrow\Services\EscrowReleaseService;
use Illuminate\Console\Command;

class ProcessEscrowReleasesCommand extends Command
{
    protected $signature = 'selloff:escrow-process-releases';

    protected $description = 'Release escrow funds to sellers when the inspection window has elapsed';

    public function handle(EscrowReleaseService $releaseService): int
    {
        $count = $releaseService->processDueReleases();

        $this->info("Processed {$count} escrow release(s).");

        return self::SUCCESS;
    }
}
