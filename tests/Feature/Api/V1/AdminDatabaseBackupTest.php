<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Services\AdminDatabaseBackupService;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDatabaseBackupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_non_super_admin_cannot_download_database_backup(): void
    {
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
    }

    public function test_database_backup_requires_super_admin_pin(): void
    {
        $this->verifiedSuperAdmin();

        $this->getJson('/api/v1/admin/database/backup')
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'SUPER_ADMIN_PIN_REQUIRED');
    }

    public function test_super_admin_can_download_database_backup_with_pin(): void
    {
        $this->verifiedSuperAdmin();

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
    }

    private function verifiedSuperAdmin(): User
    {
        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $token = $superAdmin->createToken('test', [AdminPinContext::ABILITY_VERIFIED]);
        $this->withToken($token->plainTextToken);

        return $superAdmin;
    }
}
