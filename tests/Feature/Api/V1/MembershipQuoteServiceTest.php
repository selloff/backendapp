<?php

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTermDiscount;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipQuoteService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    $this->quoteService = app(MembershipQuoteService::class);
});

test('new subscription quote applies term discount', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    clearVendorMemberships_in_MembershipQuoteService($vendor);
    $plan = basicPlan_in_MembershipQuoteService();

    $quote = $this->quoteService->quote($vendor, $plan, 3);

    expect($quote['purchase_type'])->toBe('new');
    expect($quote['monthly_price'])->toBe(10000.0);
    expect($quote['subtotal'])->toBe(30000.0);
    expect($quote['discount_percent'])->toBe(15.0);
    expect($quote['discount_amount'])->toBe(4500.0);
    expect($quote['gross_amount'])->toBe(25500.0);
    expect($quote['credit_amount'])->toBe(0.0);
    expect($quote['amount_due'])->toBe(25500.0);
});

test('extend quote charges discounted total without credit', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = basicPlan_in_MembershipQuoteService();

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'last_paid_amount' => 25500,
            'term_months' => 3,
        ],
    );

    $quote = $this->quoteService->quote($vendor, $plan, 6);

    expect($quote['purchase_type'])->toBe('extend');
    expect($quote['discount_percent'])->toBe(20.0);
    expect($quote['gross_amount'])->toBe(48000.0);
    expect($quote['credit_amount'])->toBe(0.0);
    expect($quote['amount_due'])->toBe(48000.0);
});

test('upgrade quote applies remaining subscription credit', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $basic = basicPlan_in_MembershipQuoteService();
    $premium = premiumPlan_in_MembershipQuoteService();

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

    $quote = $this->quoteService->quote($vendor, $premium, 6);

    expect($quote['purchase_type'])->toBe('upgrade');
    expect($quote['gross_amount'])->toBe(72000.0);
    expect($quote['credit_amount'])->toBe(12750.0);
    expect($quote['amount_due'])->toBe(59250.0);
});

test('expired subscription is quoted as new', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    clearVendorMemberships_in_MembershipQuoteService($vendor);
    $plan = basicPlan_in_MembershipQuoteService();

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonths(4),
            'expires_at' => now()->subDay(),
            'is_active' => true,
            'last_paid_amount' => 25500,
            'term_months' => 3,
        ],
    );

    $quote = $this->quoteService->quote($vendor, $plan, 1);

    expect($quote['purchase_type'])->toBe('new');
    expect($quote['amount_due'])->toBe(10000.0);
    expect($quote['current_membership'])->toBeNull();
});

test('active subscription rejects lower tier plan', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $basic = basicPlan_in_MembershipQuoteService();
    $premium = premiumPlan_in_MembershipQuoteService();

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $premium->id],
        [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'last_paid_amount' => 72000,
            'term_months' => 6,
        ],
    );

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $this->quoteService->quote($vendor, $basic, 3);
});

test('invalid months are rejected', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = basicPlan_in_MembershipQuoteService();

    $this->expectException(\Illuminate\Validation\ValidationException::class);
    $this->quoteService->quote($vendor, $plan, 2);
});

test('catalog lists active term discounts', function () {
    MembershipTermDiscount::query()->where('months', 6)->update(['is_active' => false]);

    $discounts = $this->quoteService->catalogTermDiscounts();

    expect($discounts)->toHaveCount(3);
    expect(array_column($discounts, 'months'))->toBe([1, 3, 12]);
});

function clearVendorMemberships_in_MembershipQuoteService(User $vendor): void
{
    UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->update(['is_active' => false]);
}

function basicPlan_in_MembershipQuoteService(): MembershipPlan
{
    return MembershipPlan::query()->updateOrCreate(
        ['title' => 'Quote Test Basic'],
        [
            'description' => 'Basic tier',
            'price' => 10000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'plan_order' => 1,
            'is_active' => true,
        ],
    );
}

function premiumPlan_in_MembershipQuoteService(): MembershipPlan
{
    return MembershipPlan::query()->updateOrCreate(
        ['title' => 'Quote Test Premium'],
        [
            'description' => 'Premium tier',
            'price' => 15000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'plan_order' => 2,
            'is_active' => true,
        ],
    );
}