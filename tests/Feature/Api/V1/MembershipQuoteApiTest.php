<?php

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('public membership plans index includes term discounts', function () {
    $this->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'plans',
                'term_discounts',
                'current_membership',
            ],
        ])
        ->assertJsonCount(4, 'data.term_discounts')
        ->assertJsonPath('data.current_membership', null);
});

test('public membership plans index includes plan features', function () {
    MembershipPlan::query()->updateOrCreate(
        ['title' => 'Feature Test Plan'],
        [
            'price' => 5000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'is_active' => true,
            'features' => ['Unlimited listings', 'Priority support'],
        ],
    );

    $this->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonFragment([
            'title' => 'Feature Test Plan',
            'features' => ['Unlimited listings', 'Priority support'],
        ]);
});

test('authenticated membership plans index includes current membership', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'current_membership' => [
                    'has_active_membership',
                    'is_expired',
                    'can_add_products',
                ],
            ],
        ]);
});

test('vendor can fetch membership quote', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);

    $plan = MembershipPlan::query()->updateOrCreate(
        ['title' => 'Quote API Basic'],
        [
            'price' => 10000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'plan_order' => 1,
            'is_active' => true,
        ],
    );

    Sanctum::actingAs($vendor);

    $this->getJson("/api/v1/membership-plans/{$plan->id}/quote?months=3")
        ->assertOk()
        ->assertJsonPath('data.purchase_type', 'new')
        ->assertJsonPath('data.months', 3)
        ->assertJsonPath('data.gross_amount', 25500)
        ->assertJsonPath('data.amount_due', 25500)
        ->assertJsonStructure([
            'data' => [
                'line_items',
                'plan' => ['id', 'monthly_price'],
            ],
        ]);
});

test('quote requires authentication', function () {
    $plan = MembershipPlan::query()->where('is_active', true)->firstOrFail();

    $this->getJson("/api/v1/membership-plans/{$plan->id}/quote?months=1")
        ->assertUnauthorized();
});

test('quote rejects invalid months', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('is_active', true)->firstOrFail();

    Sanctum::actingAs($vendor);

    $this->getJson("/api/v1/membership-plans/{$plan->id}/quote?months=5")
        ->assertUnprocessable();
});

test('quote returns extend type for active same plan', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->updateOrCreate(
        ['title' => 'Quote API Extend'],
        [
            'price' => 10000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'plan_order' => 1,
            'is_active' => true,
        ],
    );

    UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'last_paid_amount' => 10000,
            'term_months' => 1,
        ],
    );

    Sanctum::actingAs($vendor);

    $this->getJson("/api/v1/membership-plans/{$plan->id}/quote?months=12")
        ->assertOk()
        ->assertJsonPath('data.purchase_type', 'extend')
        ->assertJsonPath('data.discount_percent', 25)
        ->assertJsonPath('data.credit_amount', 0);
});
