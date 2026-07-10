<?php

use App\Models\User;
use App\Modules\Selloff\Admin\Support\AdminPinContext;
use App\Modules\Selloff\Content\Models\Page;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('super admin can verify login pin with seeded value', function () {
    $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $token = $superAdmin->createToken('test', AdminPinContext::loginAbilities($superAdmin));

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/admin-pin/verify', ['pin' => '196001'])
        ->assertOk()
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.me.admin_pin_verified', true);
});

test('admin routes require verified pin token', function () {
    $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $token = $superAdmin->createToken('test', AdminPinContext::loginAbilities($superAdmin));

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/admin/products')
        ->assertForbidden()
        ->assertJsonPath('errors.code', 'ADMIN_PIN_REQUIRED');
});

test('delete requires admin pin header', function () {
    verifiedSuperAdmin();
    $page = Page::query()->firstOrFail();

    $this->deleteJson("/api/v1/admin/cms/pages/{$page->id}")
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'ADMIN_PIN_REQUIRED');
});

test('delete succeeds with valid super admin pin', function () {
    verifiedSuperAdmin();
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
});

test('settings write requires super admin pin', function () {
    verifiedSuperAdmin();

    $this->putJson('/api/v1/settings', [
        'group' => 'general',
        'settings' => ['site_name' => 'PIN Test'],
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.code', 'SUPER_ADMIN_PIN_REQUIRED');
});

test('settings write rejects admin pin header', function () {
    verifiedSuperAdmin();

    $this->putJson('/api/v1/settings', [
        'group' => 'general',
        'settings' => ['site_name' => 'PIN Test'],
    ], [
        AdminPinContext::HEADER_ADMIN_PIN => '196001',
    ])->assertStatus(422);
});

test('settings write succeeds with super admin pin', function () {
    verifiedSuperAdmin();

    $this->putJson('/api/v1/settings', [
        'group' => 'general',
        'settings' => ['site_name' => 'PIN Test Site'],
    ], [
        AdminPinContext::HEADER_SUPER_ADMIN_PIN => '196001',
    ])->assertOk();
});

test('settings write can update product safety tips', function () {
    verifiedSuperAdmin();

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
    expect($stored['product_safety_tips'])->toBe(['Always verify the seller before paying.', 'Meet in a public place.']);
});

test('super admin can set and revoke admin user pin', function () {
    verifiedSuperAdmin();
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
    expect(Hash::check('654321', (string) $admin->admin_pin_hash))->toBeTrue();

    $this->deleteJson("/api/v1/admin/users/{$admin->id}/admin-pin")
        ->assertOk()
        ->assertJsonPath('data.revoked', true);
});

test('regular admin login pin uses assigned hash', function () {
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
});

test('login pin locks out after repeated failures', function () {
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
});

test('platform settings pages require super admin pin', function () {
    verifiedSuperAdmin();

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
});

test('platform settings pages succeed with super admin pin', function () {
    verifiedSuperAdmin();

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
});

test('login issues pending pin token for super admin', function () {
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

    expect($abilities)->toBe([AdminPinContext::ABILITY_PENDING]);
});

test('super admin delete accepts assigned admin pin', function () {
    $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $superAdmin->forceFill([
        'admin_pin_hash' => Hash::make('445566'),
        'admin_pin_set_at' => now(),
        'admin_pin_revoked_at' => null,
    ])->save();

    verifiedSuperAdmin();
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
});

test('super admin login reports when global pin not configured', function () {
    \App\Models\PlatformSetting::query()->where('key', 'super_admin_pin_hash')->delete();
    app(\App\Services\Platform\PlatformSettingsService::class)->flushCache();

    $superAdmin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $token = $superAdmin->createToken('test', AdminPinContext::loginAbilities($superAdmin));

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/admin-pin/verify', ['pin' => '196001'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pin'])
        ->assertJsonPath('errors.pin.0', fn (string $message) => str_contains($message, 'Super Admin PIN is not configured'));
});

test('bootstrap command sets missing super admin pin', function () {
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
});
