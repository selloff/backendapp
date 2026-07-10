<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin analytics returns expected structure for superadmin', function () {
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

    expect($response->json('data.kpis.orders_count'))->toBeGreaterThanOrEqual(0);
    expect($response->json('data.kpis.total_orders'))->toBeGreaterThanOrEqual($response->json('data.kpis.orders_count'));
    expect($response->json('data.kpis.platform_commission'))->toBeGreaterThanOrEqual(0);
});

test('admin analytics respects 24h period', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/analytics?period=24h')
        ->assertOk()
        ->assertJsonPath('success', true);
});

test('admin analytics respects from and to filters', function () {
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

    expect($wide['orders_count'])->toBeGreaterThanOrEqual($narrow['orders_count']);
    expect($wide['new_users'])->toBeGreaterThanOrEqual($narrow['new_users']);
});

test('admin analytics requires authentication', function () {
    $this->getJson('/api/v1/admin/analytics')->assertUnauthorized();
});

test('member cannot access admin analytics', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/admin/analytics')->assertForbidden();
});

test('limited admin omits gated breakdown sections', function () {
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

    expect($response->json('data.capabilities.orders'))->toBeFalse();
    expect($response->json('data.capabilities.reviews'))->toBeTrue();
    $this->assertArrayNotHasKey('time_series', $response->json('data'));
    expect($response->json('data.breakdowns'))->toHaveKey('reviews_by_rating');
    $this->assertArrayNotHasKey('orders_by_status', $response->json('data.breakdowns'));
});
