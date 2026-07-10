<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
    $this->artisan('selloff:migrate', ['--fresh' => true]);
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();
});

test('analytics totals include legacy paid service transactions', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['super-admin']);
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/analytics?from=2024-01-01&to=2024-12-31')
        ->assertOk();

    expect((float) $response->json('data.kpis.total_subscription_payments'))->toBe(10000.0);
    expect((float) $response->json('data.kpis.membership_revenue'))->toBe(10000.0);
    expect((float) $response->json('data.kpis.total_promotion_payments'))->toBe(2500.0);
    expect((float) $response->json('data.kpis.promotion_revenue'))->toBe(2500.0);

    $emptyPeriod = $this->getJson('/api/v1/admin/analytics?from=2020-01-01&to=2020-12-31')
        ->assertOk();

    expect((float) $emptyPeriod->json('data.kpis.total_subscription_payments'))->toBe(0.0);
    expect((float) $emptyPeriod->json('data.kpis.total_promotion_payments'))->toBe(0.0);
});

test('analytics totals include legacy success payment status', function () {
    $this->artisan('selloff:migrate', ['--fresh' => true]);
    $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();

    \Illuminate\Support\Facades\DB::table('membership_transactions')->insert([
        'id' => 8404,
        'user_id' => 102,
        'membership_plan_id' => 8401,
        'amount' => 5000.00,
        'currency_code' => 'NGN',
        'payment_method' => 'PayStack',
        'status' => 'success',
        'legacy_id' => 8404,
        'created_at' => '2024-02-01 00:00:00',
        'updated_at' => '2024-02-01 00:00:00',
    ]);

    \Illuminate\Support\Facades\DB::table('promotion_transactions')->insert([
        'id' => 8502,
        'user_id' => 102,
        'product_id' => 301,
        'amount' => 1500.00,
        'currency_code' => 'NGN',
        'payment_method' => 'PayStack',
        'status' => 'success',
        'legacy_id' => 8502,
        'created_at' => '2024-02-02 00:00:00',
        'updated_at' => '2024-02-02 00:00:00',
    ]);

    $admin = User::factory()->create();
    $admin->syncRoles(['super-admin']);
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/analytics?from=2024-01-01&to=2024-12-31')
        ->assertOk();

    expect((float) $response->json('data.kpis.total_subscription_payments'))->toBe(15000.0);
    expect((float) $response->json('data.kpis.total_promotion_payments'))->toBe(4000.0);
});