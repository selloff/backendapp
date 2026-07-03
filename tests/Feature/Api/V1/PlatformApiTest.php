<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\EssentialSeeder']);
    }

    public function test_health_endpoint_reports_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.database', true)
            ->assertJsonPath('data.storage', true);
    }

    public function test_superadmin_can_login_and_fetch_me(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'superadmin@selloff.test',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $token = $response->json('data.token');

        $me = $this->withToken($token)->getJson('/api/v1/auth/me');
        $me->assertOk()
            ->assertJsonPath('data.roles.0', 'super-admin')
            ->assertJsonPath('data.platform_settings.site_name', 'Selloff');
    }

    public function test_admin_can_manage_settings_users_roles_and_upload_media(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/settings', [
            'settings' => [
                'site_name' => 'Selloff Dev',
                'support_email' => 'support@selloff.test',
            ],
        ])->assertOk()
            ->assertJsonPath('data.settings.site_name', 'Selloff Dev');

        $this->postJson('/api/v1/users', [
            'first_name' => 'Jane',
            'last_name' => 'Vendor',
            'email' => 'jane.vendor@selloff.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => ['vendor'],
        ])->assertCreated()
            ->assertJsonPath('data.email', 'jane.vendor@selloff.test');

        $this->postJson('/api/v1/roles', [
            'name' => 'moderator',
            'permissions' => ['products', 'orders'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'moderator');

        Storage::fake('public');

        $upload = $this->postJson('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->image('avatar.jpg'),
            'context' => 'profile',
        ]);

        $upload->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['path', 'url', 'disk', 'filename']]);

        Storage::disk('public')->assertExists($upload->json('data.path'));
    }

    public function test_member_cannot_access_admin_settings(): void
    {
        $member = User::factory()->create([
            'email' => 'member@selloff.test',
            'password' => Hash::make('password'),
        ]);
        $member->assignRole('member');

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/settings')->assertForbidden();
        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_authenticated_user_can_upload_temp_media(): void
    {
        $member = User::factory()->create([
            'email' => 'uploader@selloff.test',
            'password' => Hash::make('password'),
        ]);
        $member->assignRole('member');

        Sanctum::actingAs($member);
        Storage::fake('public');

        $this->postJson('/api/v1/media/upload', [
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            'context' => 'temp',
        ])->assertCreated();
    }
}
