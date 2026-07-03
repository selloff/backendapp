<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_dashboard_returns_counts_and_lists(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);

        $response->assertJsonStructure([
            'data' => [
                'capabilities' => ['orders', 'products', 'membership', 'reviews'],
                'counts' => [
                    'orders',
                    'products',
                    'pending_products',
                    'members',
                    'vendors',
                    'logged_in_users_30_days',
                    'signup_this_month',
                    'signup_last_month',
                    'escrows',
                    'referrals',
                ],
                'latest_orders',
                'latest_transactions',
                'latest_products',
                'latest_pending_products',
                'latest_promoted_transactions',
                'latest_reviews',
                'latest_comments',
                'latest_members',
            ],
        ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.counts.orders'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.counts.members'));

        $latestIds = collect($response->json('data.latest_products'))->pluck('id');
        $pendingIds = collect($response->json('data.latest_pending_products'))->pluck('id');
        $this->assertEmpty(
            $latestIds->intersect($pendingIds),
            'Approved latest products must not overlap with pending moderation queue.',
        );
    }

    public function test_admin_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    }

    public function test_member_cannot_access_admin_dashboard(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
    }
}
