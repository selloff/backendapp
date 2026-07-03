<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Modules\Selloff\Content\Models\Page;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPinSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_super_admin_can_verify_login_pin_with_seeded_value(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $token = $superAdmin->createToken('test', AdminPinContext::loginAbilities($superAdmin));

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/admin-pin/verify', ['pin' => '196001'])
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.me.admin_pin_verified', true);
    }

    public function test_admin_routes_require_verified_pin_token(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $token = $superAdmin->createToken('test', AdminPinContext::loginAbilities($superAdmin));

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/admin/products')
            ->assertForbidden()
            ->assertJsonPath('errors.code', 'ADMIN_PIN_REQUIRED');
    }

    public function test_delete_requires_admin_pin_header(): void
    {
        $this->verifiedSuperAdmin();
        $page = Page::query()->firstOrFail();

        $this->deleteJson("/api/v1/admin/cms/pages/{$page->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'ADMIN_PIN_REQUIRED');
    }

    public function test_delete_succeeds_with_valid_super_admin_pin(): void
    {
        $this->verifiedSuperAdmin();
        $page = Page::query()->where('is_custom', true)->first();

        if ($page === null) {
            $create = $this->postJson('/api/v1/admin/cms/pages', [
                'title' => 'PIN Delete Test',
                'slug' => 'pin-delete-test',
                'content' => '<p>test</p>',
                'is_active' => true,
                'is_custom' => true,
            ])->assertCreated();

            $page = Page::query()->findOrFail($create->json('data.id'));
        }

        $this->deleteJson("/api/v1/admin/cms/pages/{$page->id}", [], [
            AdminPinContext::HEADER_ADMIN_PIN => '196001',
        ])->assertOk();
    }

    public function test_settings_write_requires_super_admin_pin(): void
    {
        $this->verifiedSuperAdmin();

        $this->putJson('/api/v1/settings', [
            'group' => 'general',
            'settings' => ['site_name' => 'PIN Test'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'SUPER_ADMIN_PIN_REQUIRED');
    }

    public function test_settings_write_rejects_admin_pin_header(): void
    {
        $this->verifiedSuperAdmin();

        $this->putJson('/api/v1/settings', [
            'group' => 'general',
            'settings' => ['site_name' => 'PIN Test'],
        ], [
            AdminPinContext::HEADER_ADMIN_PIN => '196001',
        ])->assertStatus(422);
    }

    public function test_settings_write_succeeds_with_super_admin_pin(): void
    {
        $this->verifiedSuperAdmin();

        $this->putJson('/api/v1/settings', [
            'group' => 'general',
            'settings' => ['site_name' => 'PIN Test Site'],
        ], [
            AdminPinContext::HEADER_SUPER_ADMIN_PIN => '196001',
        ])->assertOk();
    }

    public function test_settings_write_can_update_product_safety_tips(): void
    {
        $this->verifiedSuperAdmin();

        $this->putJson('/api/v1/settings', [
            'group' => 'product_listing',
            'settings' => [
                'product_safety_tips' => [
                    'Always verify the seller before paying.',
                    'Meet in a public place.',
                ],
            ],
        ], [
            AdminPinContext::HEADER_SUPER_ADMIN_PIN => '196001',
        ])
            ->assertOk()
            ->assertJsonPath('data.settings.product_safety_tips.0', 'Always verify the seller before paying.')
            ->assertJsonPath('data.settings.product_safety_tips.1', 'Meet in a public place.');

        $stored = app(PlatformSettingsService::class)->all();
        $this->assertSame(
            ['Always verify the seller before paying.', 'Meet in a public place.'],
            $stored['product_safety_tips'],
        );
    }

    public function test_super_admin_can_set_and_revoke_admin_user_pin(): void
    {
        $this->verifiedSuperAdmin();
        $admin = User::factory()->create([
            'email' => 'pin-admin@selloff.test',
            'password' => Hash::make('password'),
            'is_enable_login' => true,
            'is_disable' => false,
            'email_verified_at' => now(),
        ]);
        $admin->syncRoles(['admin']);

        $this->postJson("/api/v1/admin/users/{$admin->id}/admin-pin", [
            'pin' => '654321',
            'pin_confirmation' => '654321',
        ])->assertOk()->assertJsonPath('data.configured', true);

        $admin->refresh();
        $this->assertTrue(Hash::check('654321', (string) $admin->admin_pin_hash));

        $this->deleteJson("/api/v1/admin/users/{$admin->id}/admin-pin")
            ->assertOk()
            ->assertJsonPath('data.revoked', true);
    }

    public function test_regular_admin_login_pin_uses_assigned_hash(): void
    {
        $admin = User::factory()->create([
            'email' => 'assigned-pin@selloff.test',
            'password' => Hash::make('password'),
            'admin_pin_hash' => Hash::make('112233'),
            'admin_pin_set_at' => now(),
            'is_enable_login' => true,
            'is_disable' => false,
            'email_verified_at' => now(),
        ]);
        $admin->syncRoles(['admin']);

        $token = $admin->createToken('test', AdminPinContext::loginAbilities($admin));

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/admin-pin/verify', ['pin' => '112233'])
            ->assertOk()
            ->assertJsonPath('data.verified', true);
    }

    public function test_login_pin_locks_out_after_repeated_failures(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $token = $superAdmin->createToken('test', AdminPinContext::loginAbilities($superAdmin));

        for ($i = 0; $i < 5; $i++) {
            $this->withToken($token->plainTextToken)
                ->postJson('/api/v1/auth/admin-pin/verify', ['pin' => '000000'])
                ->assertStatus(422);
        }

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/admin-pin/verify', ['pin' => '196001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_platform_settings_pages_require_super_admin_pin(): void
    {
        $this->verifiedSuperAdmin();

        $this->putJson('/api/v1/admin/platform/cache', ['cache_system' => true])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'SUPER_ADMIN_PIN_REQUIRED');

        $this->putJson('/api/v1/admin/platform/preferences', [
            'tab' => 'shop',
            'settings' => ['auto_approve_orders' => true],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'SUPER_ADMIN_PIN_REQUIRED');

        $this->putJson('/api/v1/admin/seo', ['google_analytics' => 'UA-TEST'])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'SUPER_ADMIN_PIN_REQUIRED');

        $this->putJson('/api/v1/admin/theme', ['menu_limit' => 10])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'SUPER_ADMIN_PIN_REQUIRED');
    }

    public function test_platform_settings_pages_succeed_with_super_admin_pin(): void
    {
        $this->verifiedSuperAdmin();

        $this->putJson('/api/v1/admin/platform/cache', [
            'cache_system' => true,
            'cache_refresh_time' => 45,
        ], [
            AdminPinContext::HEADER_SUPER_ADMIN_PIN => '196001',
        ])->assertOk()->assertJsonPath('data.cache_refresh_time', 45);

        $this->putJson('/api/v1/admin/theme', [
            'menu_limit' => 12,
        ], [
            AdminPinContext::HEADER_SUPER_ADMIN_PIN => '196001',
        ])->assertOk()->assertJsonPath('data.menu_limit', 12);
    }

    public function test_login_issues_pending_pin_token_for_super_admin(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'superadmin@selloff.test',
            'password' => 'password',
            'device_name' => 'test',
        ])
            ->assertOk()
            ->assertJsonPath('data.me.admin_pin_required', true)
            ->assertJsonPath('data.me.admin_pin_verified', false)
            ->assertJsonPath('data.me.admin_pin_type', 'super');

        $tokenId = (int) explode('|', (string) $response->json('data.token'), 2)[0];
        $abilities = \Laravel\Sanctum\PersonalAccessToken::query()->findOrFail($tokenId)->abilities;

        $this->assertSame([AdminPinContext::ABILITY_PENDING], $abilities);
    }

    public function test_super_admin_delete_accepts_assigned_admin_pin(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $superAdmin->forceFill([
            'admin_pin_hash' => Hash::make('445566'),
            'admin_pin_set_at' => now(),
            'admin_pin_revoked_at' => null,
        ])->save();

        $this->verifiedSuperAdmin();
        $page = Page::query()->where('is_custom', true)->first();

        if ($page === null) {
            $create = $this->postJson('/api/v1/admin/cms/pages', [
                'title' => 'Admin PIN Delete Test',
                'slug' => 'admin-pin-delete-test',
                'content' => '<p>test</p>',
                'is_active' => true,
                'is_custom' => true,
            ])->assertCreated();

            $page = Page::query()->findOrFail($create->json('data.id'));
        }

        $this->deleteJson("/api/v1/admin/cms/pages/{$page->id}", [], [
            AdminPinContext::HEADER_ADMIN_PIN => '445566',
        ])->assertOk();
    }

    public function test_super_admin_login_reports_when_global_pin_not_configured(): void
    {
        \App\Models\PlatformSetting::query()->where('key', 'super_admin_pin_hash')->delete();
        app(\App\Services\Platform\PlatformSettingsService::class)->flushCache();

        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $token = $superAdmin->createToken('test', AdminPinContext::loginAbilities($superAdmin));

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/admin-pin/verify', ['pin' => '196001'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pin'])
            ->assertJsonPath('errors.pin.0', fn (string $message) => str_contains($message, 'Super Admin PIN is not configured'));
    }

    public function test_bootstrap_command_sets_missing_super_admin_pin(): void
    {
        \App\Models\PlatformSetting::query()->where('key', 'super_admin_pin_hash')->delete();
        app(\App\Services\Platform\PlatformSettingsService::class)->flushCache();

        $this->artisan('selloff:bootstrap-super-admin-pin', ['pin' => '196001'])
            ->assertSuccessful();

        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $token = $superAdmin->createToken('test', AdminPinContext::loginAbilities($superAdmin));

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/admin-pin/verify', ['pin' => '196001'])
            ->assertOk()
            ->assertJsonPath('data.verified', true);
    }

    private function verifiedSuperAdmin(): User
    {
        $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $token = $superAdmin->createToken('test', [AdminPinContext::ABILITY_VERIFIED]);
        $this->withToken($token->plainTextToken);

        return $superAdmin;
    }
}
