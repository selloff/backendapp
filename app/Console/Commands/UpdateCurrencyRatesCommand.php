<?php

namespace App\Console\Commands;

use App\Services\Platform\ExchangeRateUpdateService;
use Illuminate\Console\Command;

class UpdateCurrencyRatesCommand extends Command
{
    protected $signature = 'selloff:update-currency-rates';

    protected $description = 'Refresh currency exchange rates when auto-update is enabled';

    public function handle(ExchangeRateUpdateService $service): int
    {
        if (! $service->shouldAutoUpdate()) {
            $this->info('Auto-update exchange rates is disabled or currency converter is off.');

            return self::SUCCESS;
        }

        $result = $service->update();
        $this->info($result['message']);

        return self::SUCCESS;
    }
}
