<?php

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTermDiscount;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipExpiryNotificationService;
use App\Modules\Selloff\Payment\Services\MembershipQuoteService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin discount change is reflected in vendor quote', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = basicPlan_in_MembershipSubscribeSystem();

    UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);

    Sanctum::actingAs($admin);
    $this->putJson('/api/v1/admin/membership-term-discounts', [
        'discounts' => [
            ['months' => 1, 'discount_percent' => 0, 'is_active' => true],
            ['months' => 3, 'discount_percent' => 22, 'is_active' => true],
            ['months' => 6, 'discount_percent' => 20, 'is_active' => true],
            ['months' => 12, 'discount_percent' => 25, 'is_active' => true],
        ],
    ])->assertOk();

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonPath('data.term_discounts.1.discount_percent', '22.00');

    $this->getJson("/api/v1/membership-plans/{$plan->id}/quote?months=3")
        ->assertOk()
        ->assertJsonPath('data.purchase_type', 'new')
        ->assertJsonPath('data.discount_percent', 22)
        ->assertJsonPath('data.gross_amount', 23400)
        ->assertJsonPath('data.amount_due', 23400);
});

test('membership catalog and resume payment endpoints are available', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = basicPlan_in_MembershipSubscribeSystem();

    UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);

    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'plans',
                'term_discounts',
                'current_membership',
            ],
        ])
        ->assertJsonCount(4, 'data.term_discounts');

    $purchase = $this->postJson("/api/v1/membership-plans/{$plan->id}/purchase", [
        'months' => 1,
        'payment_method' => 'bank_transfer',
    ])->assertCreated();

    $transactionId = (int) $purchase->json('data.id');

    $this->postJson("/api/v1/vendor/membership/transactions/{$transactionId}/resume-payment", [
        'payment_method' => 'bank_transfer',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $this->getJson("/api/v1/vendor/membership/transactions/{$transactionId}/invoice")
        ->assertOk()
        ->assertJsonPath('data.can_complete_payment', true)
        ->assertJsonPath('data.term_months', 1);
});

test('quote service covers new extend and upgrade paths', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $basic = basicPlan_in_MembershipSubscribeSystem();
    $premium = premiumPlan_in_MembershipSubscribeSystem();
    $quoteService = app(MembershipQuoteService::class);

    UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);

    $newQuote = $quoteService->quote($vendor, $basic, 1);
    expect($newQuote['purchase_type'])->toBe('new');

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $basic->id],
        [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'last_paid_amount' => 10000,
            'term_months' => 1,
        ],
    );

    $extendQuote = $quoteService->quote($vendor, $basic, 3);
    expect($extendQuote['purchase_type'])->toBe('extend');
    expect($extendQuote['credit_amount'])->toBe(0.0);

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $basic->id],
        [
            'starts_at' => now()->subDays(45),
            'expires_at' => now()->addDays(45),
            'is_active' => true,
            'last_paid_amount' => 25500,
            'term_months' => 3,
        ],
    );

    $upgradeQuote = $quoteService->quote($vendor, $premium, 6);
    expect($upgradeQuote['purchase_type'])->toBe('upgrade');
    expect($upgradeQuote['credit_amount'])->toBeGreaterThan(0);
    expect((float) $upgradeQuote['amount_due'])->toBeLessThan((float) $upgradeQuote['gross_amount']);
});

test('expiry notification renew url points to subscribe page', function () {
    config(['selloff.spa_url' => 'https://shop.selloff.test']);

    expect(app(MembershipExpiryNotificationService::class)->renewUrl())->toBe('https://shop.selloff.test/vendor/membership/subscribe');
});

test('inactive term lengths are excluded from catalog', function () {
    MembershipTermDiscount::query()->where('months', 6)->update(['is_active' => false]);

    $this->getJson('/api/v1/membership-plans')
        ->assertOk()
        ->assertJsonCount(3, 'data.term_discounts');
});

function basicPlan_in_MembershipSubscribeSystem(): MembershipPlan
{
    return MembershipPlan::query()->updateOrCreate(
        ['title' => 'System Test Basic'],
        [
            'price' => 10000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'plan_order' => 1,
            'is_active' => true,
        ],
    );
}

function premiumPlan_in_MembershipSubscribeSystem(): MembershipPlan
{
    return MembershipPlan::query()->updateOrCreate(
        ['title' => 'System Test Premium'],
        [
            'price' => 15000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'plan_order' => 2,
            'is_active' => true,
        ],
    );
}
