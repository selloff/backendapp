<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTermDiscount;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipExpiryNotificationService;
use App\Modules\Selloff\Payment\Services\MembershipQuoteService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MembershipSubscribeSystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_admin_discount_change_is_reflected_in_vendor_quote(): void
    {
        $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = $this->basicPlan();

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
    }

    public function test_membership_catalog_and_resume_payment_endpoints_are_available(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = $this->basicPlan();

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
    }

    public function test_quote_service_covers_new_extend_and_upgrade_paths(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $basic = $this->basicPlan();
        $premium = $this->premiumPlan();
        $quoteService = app(MembershipQuoteService::class);

        UserMembershipPlan::query()->where('user_id', $vendor->id)->update(['is_active' => false]);

        $newQuote = $quoteService->quote($vendor, $basic, 1);
        $this->assertSame('new', $newQuote['purchase_type']);

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
        $this->assertSame('extend', $extendQuote['purchase_type']);
        $this->assertSame(0.0, $extendQuote['credit_amount']);

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
        $this->assertSame('upgrade', $upgradeQuote['purchase_type']);
        $this->assertGreaterThan(0, $upgradeQuote['credit_amount']);
        $this->assertLessThan((float) $upgradeQuote['gross_amount'], (float) $upgradeQuote['amount_due']);
    }

    public function test_expiry_notification_renew_url_points_to_subscribe_page(): void
    {
        config(['selloff.spa_url' => 'https://shop.selloff.test']);

        $this->assertSame(
            'https://shop.selloff.test/vendor/membership/subscribe',
            app(MembershipExpiryNotificationService::class)->renewUrl(),
        );
    }

    public function test_inactive_term_lengths_are_excluded_from_catalog(): void
    {
        MembershipTermDiscount::query()->where('months', 6)->update(['is_active' => false]);

        $this->getJson('/api/v1/membership-plans')
            ->assertOk()
            ->assertJsonCount(3, 'data.term_discounts');
    }

    private function basicPlan(): MembershipPlan
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

    private function premiumPlan(): MembershipPlan
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
}
