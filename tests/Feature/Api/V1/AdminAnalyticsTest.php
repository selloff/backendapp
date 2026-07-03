<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_analytics_returns_expected_structure_for_superadmin(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/analytics')
            ->assertOk()
            ->assertJsonPath('success', true);

        $response->assertJsonStructure([
            'data' => [
                'period' => ['from', 'to', 'previous_from', 'previous_to'],
                'capabilities' => ['orders', 'products', 'membership', 'reviews', 'comments', 'earnings'],
                'kpis' => [
                    'gmv',
                    'platform_commission',
                    'orders_count',
                    'total_orders',
                    'avg_order_value',
                    'completed_orders',
                    'cancelled_orders',
                    'paid_orders',
                    'new_users',
                    'new_vendors',
                    'signup_this_month',
                    'signup_last_month',
                    'total_members',
                    'total_vendors',
                    'active_users_30d',
                    'pending_products',
                    'listed_products',
                    'pending_payouts',
                    'open_refunds',
                    'open_support_tickets',
                    'escrow_active',
                    'escrow_total',
                    'promotion_revenue',
                    'membership_revenue',
                    'total_promotion_payments',
                    'total_subscription_payments',
                    'total_wallet_balance',
                    'affiliate_members',
                    'contact_views',
                    'impressions',
                    'deltas' => ['gmv_pct', 'orders_pct', 'new_users_pct'],
                ],
                'time_series' => [
                    'revenue',
                    'orders',
                    'signups',
                ],
                'breakdowns' => [
                    'orders_by_status',
                    'payments_by_method',
                    'products_by_status',
                    'top_categories',
                    'top_vendors',
                    'escrow_by_status',
                    'reviews_by_rating',
                ],
            ],
        ]);

        $this->assertGreaterThanOrEqual(0, $response->json('data.kpis.orders_count'));
        $this->assertGreaterThanOrEqual(
            $response->json('data.kpis.orders_count'),
            $response->json('data.kpis.total_orders'),
        );
        $this->assertGreaterThanOrEqual(0, $response->json('data.kpis.platform_commission'));
    }

    public function test_admin_analytics_respects_24h_period(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/analytics?period=24h')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_analytics_respects_from_and_to_filters(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $wide = $this->getJson('/api/v1/admin/analytics?from=2015-01-01&to='.now()->toDateString())
            ->assertOk()
            ->json('data.kpis');

        $narrow = $this->getJson('/api/v1/admin/analytics?from=2026-01-01&to=2026-01-31')
            ->assertOk()
            ->assertJsonPath('data.period.from', '2026-01-01')
            ->assertJsonPath('data.period.to', '2026-01-31')
            ->json('data.kpis');

        $this->assertGreaterThanOrEqual(
            $narrow['orders_count'],
            $wide['orders_count'],
        );
        $this->assertGreaterThanOrEqual(
            $narrow['new_users'],
            $wide['new_users'],
        );
    }

    public function test_admin_analytics_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/analytics')->assertUnauthorized();
    }

    public function test_member_cannot_access_admin_analytics(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/admin/analytics')->assertForbidden();
    }

    public function test_limited_admin_omits_gated_breakdown_sections(): void
    {
        $role = Role::query()->firstOrCreate(['name' => 'reviews-only-analytics', 'guard_name' => 'web']);
        $role->syncPermissions(['admin_panel', 'reviews']);

        $user = User::query()->create([
            'first_name' => 'Reviews',
            'last_name' => 'Only',
            'slug' => 'reviews-only-analytics',
            'email' => 'reviews-only-analytics@selloff.test',
            'password' => Hash::make('password'),
            'is_enable_login' => true,
            'is_disable' => false,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/admin/analytics')->assertOk();

        $this->assertFalse($response->json('data.capabilities.orders'));
        $this->assertTrue($response->json('data.capabilities.reviews'));
        $this->assertArrayNotHasKey('time_series', $response->json('data'));
        $this->assertArrayHasKey('reviews_by_rating', $response->json('data.breakdowns'));
        $this->assertArrayNotHasKey('orders_by_status', $response->json('data.breakdowns'));
    }
}
