<?php

use App\Models\User;
use App\Services\Platform\PlatformSettingsService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('bootstrap after pg restore syncs permissions and super admin pin', function () {
    app(PlatformSettingsService::class)->upsertMany(['super_admin_pin_hash' => null], 'security');

    $this->artisan('selloff:bootstrap-after-pg-restore', ['--pin' => '196001'])
        ->assertSuccessful();

    $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();

    expect($superAdmin->can('admin_panel'))->toBeTrue();
    expect(app(PlatformSettingsService::class)->all()['super_admin_pin_hash'] !== null)->toBeTrue();
});

test('diagnose admin auth reports super admin readiness', function () {
    $this->artisan('selloff:diagnose-admin-auth', ['email' => 'superadmin@selloff.test'])
        ->assertSuccessful()
        ->expectsOutputToContain('can admin_panel');
});

test('diagnose admin auth flags missing admin panel', function () {
    $user = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $this->artisan('selloff:diagnose-admin-auth', ['--id' => (string) $user->id])
        ->assertFailed()
        ->expectsOutputToContain('Missing admin_panel');
});
