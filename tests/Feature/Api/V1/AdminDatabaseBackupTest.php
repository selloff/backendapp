<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Services\AdminDatabaseBackupService;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('non super admin cannot download database backup', function () {
    $admin = User::factory()->create([
        'email' => 'backup-admin@selloff.test',
        'password' => Hash::make('password'),
        'is_enable_login' => true,
        'is_disable' => false,
        'email_verified_at' => now(),
    ]);
    $admin->syncRoles(['admin']);
    $token = $admin->createToken('test', [AdminPinContext::ABILITY_VERIFIED]);

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/admin/database/backup')
        ->assertForbidden();
});

test('database backup requires super admin pin', function () {
    verifiedSuperAdmin();

    $this->getJson('/api/v1/admin/database/backup')
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'SUPER_ADMIN_PIN_REQUIRED');
});

test('super admin can download database backup with pin', function () {
    verifiedSuperAdmin();

    $this->mock(AdminDatabaseBackupService::class, function ($mock): void {
        $mock->shouldReceive('download')
            ->once()
            ->andReturn(response()->streamDownload(function (): void {
                echo '-- Selloff test backup';
            }, 'db_backup-test.sql', [
                'Content-Type' => 'application/sql; charset=UTF-8',
            ]));
    });

    $response = $this->get('/api/v1/admin/database/backup', [
        AdminPinContext::HEADER_SUPER_ADMIN_PIN => '196001',
    ]);

    $response->assertOk();
    $response->assertHeader('content-disposition');
    $this->assertStringContainsString('-- Selloff test backup', $response->streamedContent());
});
