<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Admin\Models\Currency;
use App\Modules\Selloff\Catalog\Models\Category;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Wave3AdminPlatformTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_currency_rate_refresh_updates_exchange_rates_when_converter_configured(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        app(PlatformSettingsService::class)->upsertMany([
            'currency_converter' => true,
            'auto_update_exchange_rates' => true,
            'default_currency' => 'NGN',
            'currency_converter_api' => 'fixer',
            'currency_converter_api_key' => 'test-key',
        ], 'payment');

        Http::fake([
            'data.fixer.io/*' => Http::response([
                'rates' => [
                    'NGN' => 1,
                    'USD' => 0.0012,
                    'EUR' => 0.0011,
                ],
            ]),
        ]);

        $this->postJson('/api/v1/admin/currencies/refresh-rates', [], $this->superAdminPinHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.skipped', false)
            ->assertJsonPath('data.updated', 2);

        $this->assertSame('0.001200', Currency::query()->where('code', 'USD')->value('exchange_rate'));
    }

    public function test_homepage_category_layout_can_sync_featured_and_index_order(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $categories = Category::query()->whereNull('parent_id')->orderBy('id')->limit(3)->get();
        $this->assertGreaterThanOrEqual(2, $categories->count());

        $featuredIds = $categories->take(2)->pluck('id')->reverse()->values()->all();
        $indexIds = $categories->pluck('id')->values()->all();

        $this->putJson('/api/v1/admin/cms/homepage/featured-categories', [
            'category_ids' => $featuredIds,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.featured_ids', $featuredIds);

        $this->putJson('/api/v1/admin/cms/homepage/index-categories', [
            'category_ids' => $indexIds,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.index_ids', $indexIds);

        $firstFeatured = Category::query()->findOrFail($featuredIds[0]);
        $this->assertTrue($firstFeatured->is_featured);
        $this->assertSame(1, (int) $firstFeatured->featured_order);
    }

    public function test_granular_permission_allows_homepage_manager_without_full_admin_panel(): void
    {
        Permission::query()->firstOrCreate(['name' => 'homepage_manager', 'guard_name' => 'web']);
        $role = Role::query()->firstOrCreate(['name' => 'homepage-only', 'guard_name' => 'web']);
        $role->syncPermissions(['homepage_manager']);

        $user = User::query()->create([
            'first_name' => 'Homepage',
            'last_name' => 'Editor',
            'slug' => 'homepage-editor',
            'email' => 'homepage-editor@selloff.test',
            'password' => bcrypt('password'),
            'is_enable_login' => true,
            'is_disable' => false,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/cms/homepage/category-layout')->assertOk();
        $this->getJson('/api/v1/admin/currencies')->assertForbidden();
    }

    public function test_platform_cache_preferences_and_abuse_reports_endpoints(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/platform/cache', [
            'cache_system' => true,
            'cache_refresh_time' => 30,
        ], $this->superAdminPinHeaders())
            ->assertOk()
            ->assertJsonPath('data.cache_system', true)
            ->assertJsonPath('data.cache_refresh_time', 30);

        $this->putJson('/api/v1/admin/platform/preferences', [
            'tab' => 'shop',
            'settings' => [
                'auto_approve_orders' => true,
            ],
        ], $this->superAdminPinHeaders())
            ->assertOk()
            ->assertJsonPath('data.shop.auto_approve_orders', true);

        $product = DB::table('products')->first();
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

        DB::table('abuse_reports')->insert([
            'reporter_id' => $buyer->id,
            'product_id' => $product->id,
            'item_id' => $product->id,
            'report_type' => 'product',
            'description' => 'Demo abuse report',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/abuse-reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['description' => 'Demo abuse report'])
            ->assertJsonFragment(['content_type_label' => 'Product']);

        $reportId = (int) DB::table('abuse_reports')->orderByDesc('id')->value('id');

        $this->patchJson("/api/v1/admin/abuse-reports/{$reportId}", ['status' => 'reviewed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');
    }

    public function test_payment_gateway_settings_support_wallet_deposit_depth(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/payments/gateways', [
            'wallet_deposit_enabled' => true,
            'wallet_min_deposit' => 500,
        ], $this->superAdminPinHeaders())
            ->assertOk()
            ->assertJsonPath('data.gateway_settings.wallet_deposit_enabled', true)
            ->assertJsonPath('data.gateway_settings.wallet_min_deposit', 500);
    }
}
