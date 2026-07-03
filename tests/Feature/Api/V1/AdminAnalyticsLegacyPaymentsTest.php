<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAnalyticsLegacyPaymentsTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = base_path('tests/fixtures/legacy-subset.sql');
        $this->artisan('selloff:migrate', ['--fresh' => true]);
        $this->artisan('selloff:import-legacy-data', ['--source' => $this->fixture])->assertSuccessful();
    }

    public function test_analytics_totals_include_legacy_paid_service_transactions(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['super-admin']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/analytics?from=2024-01-01&to=2024-12-31')
            ->assertOk();

        $this->assertSame(10000.0, (float) $response->json('data.kpis.total_subscription_payments'));
        $this->assertSame(10000.0, (float) $response->json('data.kpis.membership_revenue'));
        $this->assertSame(2500.0, (float) $response->json('data.kpis.total_promotion_payments'));
        $this->assertSame(2500.0, (float) $response->json('data.kpis.promotion_revenue'));

        $emptyPeriod = $this->getJson('/api/v1/admin/analytics?from=2020-01-01&to=2020-12-31')
            ->assertOk();

        $this->assertSame(0.0, (float) $emptyPeriod->json('data.kpis.total_subscription_payments'));
        $this->assertSame(0.0, (float) $emptyPeriod->json('data.kpis.total_promotion_payments'));
    }

    public function test_analytics_totals_include_legacy_success_payment_status(): void
    {
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

        $this->assertSame(15000.0, (float) $response->json('data.kpis.total_subscription_payments'));
        $this->assertSame(4000.0, (float) $response->json('data.kpis.total_promotion_payments'));
    }
}
