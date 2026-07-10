<?php

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('wallet purchase applies term discount and extends membership', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();
    $vendor->update(['wallet_balance' => 100000]);

    $existingExpiry = now()->addDays(10);
    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonth(),
            'expires_at' => $existingExpiry,
            'is_active' => true,
            'last_paid_amount' => 9999,
            'term_months' => 1,
        ],
    );

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/membership-plans/{$plan->id}/purchase", [
        'months' => 3,
        'payment_method' => 'wallet_balance',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.purchase_type', 'extend')
        ->assertJsonPath('data.amount', '24225.00');

    $subscription = UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->where('membership_plan_id', $plan->id)
        ->firstOrFail();

    expect($subscription->expires_at->greaterThan($existingExpiry->copy()->addMonths(2)))->toBeTrue();
    expect((int) $subscription->term_months)->toBe(3);
    $this->assertDatabaseHas('membership_transactions', [
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'purchase_type' => 'extend',
        'term_months' => 3,
        'status' => 'completed',
    ]);
});

test('bank transfer creates pending transaction without activation', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    clearVendorMemberships_in_MembershipCheckout($vendor);
    $plan = basicPlan_in_MembershipCheckout();
    $vendor->update(['wallet_balance' => 0]);

    Sanctum::actingAs($vendor);

    $this->postJson("/api/v1/membership-plans/{$plan->id}/purchase", [
        'months' => 1,
        'payment_method' => 'bank_transfer',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.purchase_type', 'new')
        ->assertJsonPath('data.amount', '10000.00');

    expect(UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->where('membership_plan_id', $plan->id)
        ->where('is_active', true)
        ->where('expires_at', '>', now())
        ->first())->toBeNull();
});

test('admin can approve pending membership and activate subscription', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    clearVendorMemberships_in_MembershipCheckout($vendor);
    $plan = basicPlan_in_MembershipCheckout();

    $transaction = MembershipTransaction::query()->create([
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'amount' => 25500,
        'amount_charged' => 25500,
        'gross_amount' => 25500,
        'discount_amount' => 4500,
        'credit_amount' => 0,
        'term_months' => 3,
        'purchase_type' => 'new',
        'monthly_price_at_purchase' => 10000,
        'currency_code' => 'NGN',
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
        'metadata' => [
            'quote' => [
                'purchase_type' => 'new',
                'months' => 3,
                'amount_due' => 25500,
            ],
        ],
    ]);

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson("/api/v1/admin/membership/transactions/{$transaction->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $subscription = UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->where('membership_plan_id', $plan->id)
        ->firstOrFail();

    expect($subscription->is_active)->toBeTrue();
    expect($subscription->expires_at->greaterThan(now()->addMonths(2)))->toBeTrue();
});

test('paystack completion activates upgraded membership', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $basic = basicPlan_in_MembershipCheckout();
    $premium = premiumPlan_in_MembershipCheckout();

    UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);
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

    $vendor->update(['wallet_balance' => 0]);
    Sanctum::actingAs($vendor);

    app(PaymentGatewaySettingsService::class)->updateLegacyGateway('paystack', [
        'status' => true,
        'public_key' => 'pk_test_demo',
        'secret_key' => 'sk_test_demo',
    ]);

    $response = $this->postJson("/api/v1/membership-plans/{$premium->id}/purchase", [
        'months' => 6,
        'payment_method' => 'paystack',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.purchase_type', 'upgrade')
        ->assertJsonPath('data.requires_action', true);

    $transactionId = (int) $response->json('data.id');
    $reference = (string) $response->json('data.action.reference');
    $amount = (float) $response->json('data.amount');

    Http::fake([
        'api.paystack.co/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => (int) round($amount * 100),
                'currency' => 'NGN',
                'reference' => $reference,
            ],
        ]),
    ]);

    $this->postJson("/api/v1/vendor/membership/transactions/{$transactionId}/paystack/complete", [
        'payment_reference' => $reference,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $subscription = UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->where('membership_plan_id', $premium->id)
        ->where('is_active', true)
        ->firstOrFail();

    expect($subscription->expires_at->greaterThan(now()->addMonths(5)))->toBeTrue();
    expect(UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->where('membership_plan_id', $basic->id)
        ->where('is_active', true)
        ->exists())->toBeFalse();
});

test('pending membership invoice supports complete payment', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    clearVendorMemberships_in_MembershipCheckout($vendor);
    $plan = basicPlan_in_MembershipCheckout();

    $transaction = MembershipTransaction::query()->create([
        'user_id' => $vendor->id,
        'membership_plan_id' => $plan->id,
        'amount' => 10000,
        'amount_charged' => 10000,
        'term_months' => 1,
        'purchase_type' => 'new',
        'currency_code' => 'NGN',
        'payment_method' => 'bank_transfer',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($vendor);

    $this->getJson("/api/v1/vendor/membership/transactions/{$transaction->id}/invoice")
        ->assertOk()
        ->assertJsonPath('data.is_pending_payment', true)
        ->assertJsonPath('data.can_complete_payment', true)
        ->assertJsonPath('data.term_months', 1);
});

function clearVendorMemberships_in_MembershipCheckout(User $vendor): void
{
    UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->update(['is_active' => false]);
}

function basicPlan_in_MembershipCheckout(): MembershipPlan
{
    return MembershipPlan::query()->updateOrCreate(
        ['title' => 'Checkout Test Basic'],
        [
            'price' => 10000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'plan_order' => 1,
            'is_active' => true,
        ],
    );
}

function premiumPlan_in_MembershipCheckout(): MembershipPlan
{
    return MembershipPlan::query()->updateOrCreate(
        ['title' => 'Checkout Test Premium'],
        [
            'price' => 15000,
            'currency_code' => 'NGN',
            'duration_days' => 30,
            'plan_order' => 2,
            'is_active' => true,
        ],
    );
}
