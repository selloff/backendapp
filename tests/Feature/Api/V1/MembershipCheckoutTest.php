<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\PaymentGatewaySettingsService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipCheckoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_wallet_purchase_applies_term_discount_and_extends_membership(): void
    {
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
            ->assertJsonPath('data.amount', '25497.45');

        $subscription = UserMembershipPlan::query()
            ->where('user_id', $vendor->id)
            ->where('membership_plan_id', $plan->id)
            ->firstOrFail();

        $this->assertTrue($subscription->expires_at->greaterThan($existingExpiry->copy()->addMonths(2)));
        $this->assertSame(3, (int) $subscription->term_months);
        $this->assertDatabaseHas('membership_transactions', [
            'user_id' => $vendor->id,
            'membership_plan_id' => $plan->id,
            'purchase_type' => 'extend',
            'term_months' => 3,
            'status' => 'completed',
        ]);
    }

    public function test_bank_transfer_creates_pending_transaction_without_activation(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $this->clearVendorMemberships($vendor);
        $plan = $this->basicPlan();
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

        $this->assertNull(
            UserMembershipPlan::query()
                ->where('user_id', $vendor->id)
                ->where('membership_plan_id', $plan->id)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->first(),
        );
    }

    public function test_admin_can_approve_pending_membership_and_activate_subscription(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $this->clearVendorMemberships($vendor);
        $plan = $this->basicPlan();

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

        $this->assertTrue($subscription->is_active);
        $this->assertTrue($subscription->expires_at->greaterThan(now()->addMonths(2)));
    }

    public function test_paystack_completion_activates_upgraded_membership(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $basic = $this->basicPlan();
        $premium = $this->premiumPlan();

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

        $this->assertTrue($subscription->expires_at->greaterThan(now()->addMonths(5)));
        $this->assertFalse(
            UserMembershipPlan::query()
                ->where('user_id', $vendor->id)
                ->where('membership_plan_id', $basic->id)
                ->where('is_active', true)
                ->exists(),
        );
    }

    public function test_pending_membership_invoice_supports_complete_payment(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $this->clearVendorMemberships($vendor);
        $plan = $this->basicPlan();

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
    }

    private function clearVendorMemberships(User $vendor): void
    {
        UserMembershipPlan::query()
            ->where('user_id', $vendor->id)
            ->update(['is_active' => false]);
    }

    private function basicPlan(): MembershipPlan
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

    private function premiumPlan(): MembershipPlan
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
}
