<?php

namespace App\Console\Commands;

use App\Modules\Selloff\Admin\Services\SuperAdminPinBootstrap;
use App\Services\Auth\RolePermissionSync;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class BootstrapAfterPgRestoreCommand extends Command
{
    protected $signature = 'selloff:bootstrap-after-pg-restore
                            {--pin= : Six-digit Super Admin PIN when bootstrapping (defaults to SUPER_ADMIN_BOOTSTRAP_PIN or 196001)}
                            {--force-pin : Overwrite an existing Super Admin PIN hash}
                            {--email= : Diagnose this admin user after bootstrap}';

    protected $description = 'Repair Spatie roles, permission cache, and Super Admin PIN after a raw pg_restore (skips Laravel ETL)';

    public function handle(
        RolePermissionSync $sync,
        SuperAdminPinBootstrap $pinBootstrap,
    ): int {
        $this->info('Syncing Spatie roles and permissions…');
        $sync->sync();

        $this->info('Resetting permission cache…');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->callSilent('permission:cache-reset');

        $pin = (string) ($this->option('pin') ?: config('app.super_admin_bootstrap_pin', '196001'));

        if ($this->option('force-pin')) {
            $pinBootstrap->forceSet($pin);
            $this->info('Super Admin PIN hash updated.');
        } elseif ($pinBootstrap->isConfigured()) {
            $this->info('Super Admin PIN is already configured.');
        } elseif ($pinBootstrap->ensureConfigured($pin)) {
            $this->info('Super Admin PIN bootstrapped.');
        } else {
            $this->warn('Super Admin PIN was not bootstrapped. Pass --pin=###### or set SUPER_ADMIN_BOOTSTRAP_PIN.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Next steps on staging:');
        $this->line('  1. Sign out of the SPA and sign in again.');
        $this->line('  2. Complete /admin/pin-verify (default demo PIN: 196001).');
        $this->line('  3. Retry the failing admin API call.');
        $this->newLine();
        $this->line('If admin routes still return 403 Forbidden., re-import roles + users:');
        $this->line('  php artisan selloff:import-legacy-data --source=PATH --table=roles_permissions');
        $this->line('  php artisan selloff:import-legacy-data --source=PATH --table=users');

        if ($email = $this->option('email')) {
            $this->newLine();
            $this->call('selloff:diagnose-admin-auth', ['email' => $email]);
        }

        return self::SUCCESS;
    }
}
