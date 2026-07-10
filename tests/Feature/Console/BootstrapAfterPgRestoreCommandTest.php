<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Services\Platform\PlatformSettingsService;
use Tests\TestCase;

class BootstrapAfterPgRestoreCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_bootstrap_after_pg_restore_syncs_permissions_and_super_admin_pin(): void
    {
        app(PlatformSettingsService::class)->upsertMany(['super_admin_pin_hash' => null], 'security');

        $this->artisan('selloff:bootstrap-after-pg-restore', ['--pin' => '196001'])
            ->assertSuccessful();

        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

        $this->assertTrue($superAdmin->can('admin_panel'));
        $this->assertTrue(app(PlatformSettingsService::class)->all()['super_admin_pin_hash'] !== null);
    }

    public function test_diagnose_admin_auth_reports_super_admin_readiness(): void
    {
        $this->artisan('selloff:diagnose-admin-auth', ['email' => 'superadmin@selloff.test'])
            ->assertSuccessful()
            ->expectsOutputToContain('can admin_panel');
    }

    public function test_diagnose_admin_auth_flags_missing_admin_panel(): void
    {
        $user = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

        $this->artisan('selloff:diagnose-admin-auth', ['--id' => (string) $user->id])
            ->assertFailed()
            ->expectsOutputToContain('Missing admin_panel');
    }
}
