<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin dashboard returns counts and lists', function () {
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

    expect($response->json('data.counts.orders'))->toBeGreaterThanOrEqual(1);
    expect($response->json('data.counts.members'))->toBeGreaterThanOrEqual(1);

    $latestIds = collect($response->json('data.latest_products'))->pluck('id');
    $pendingIds = collect($response->json('data.latest_pending_products'))->pluck('id');
    expect($latestIds->intersect($pendingIds))->toBeEmpty('Approved latest products must not overlap with pending moderation queue.');
});

test('admin dashboard requires authentication', function () {
    $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
});

test('member cannot access admin dashboard', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
});
