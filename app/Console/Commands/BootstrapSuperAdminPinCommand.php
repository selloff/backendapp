<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Admin\Services\SuperAdminPinBootstrap;
use Illuminate\Console\Command;

class BootstrapSuperAdminPinCommand extends Command
{
    protected $signature = 'selloff:bootstrap-super-admin-pin
                            {pin? : Six-digit PIN (defaults to SUPER_ADMIN_BOOTSTRAP_PIN or 196001 in local)}
                            {--force : Overwrite an existing Super Admin PIN hash}';

    protected $description = 'Bootstrap the global Super Admin PIN used at admin login';

    public function handle(SuperAdminPinBootstrap $bootstrap): int
    {
        $pin = (string) ($this->argument('pin') ?: config('app.super_admin_bootstrap_pin', '196001'));

        if ($this->option('force')) {
            $bootstrap->forceSet($pin);
            $this->info('Super Admin PIN hash updated.');

            return self::SUCCESS;
        }

        if ($bootstrap->isConfigured()) {
            $this->info('Super Admin PIN is already configured.');

            return self::SUCCESS;
        }

        if (! $bootstrap->ensureConfigured($pin)) {
            $this->error('Could not bootstrap Super Admin PIN. Provide a 6-digit pin argument or set SUPER_ADMIN_BOOTSTRAP_PIN.');

            return self::FAILURE;
        }

        $this->info('Super Admin PIN bootstrapped successfully.');

        return self::SUCCESS;
    }
}
