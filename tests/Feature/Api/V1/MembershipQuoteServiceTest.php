<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\MembershipTermDiscount;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipQuoteService;
use Tests\TestCase;

class MembershipQuoteServiceTest extends TestCase
{
    private MembershipQuoteService $quoteService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
        $this->quoteService = app(MembershipQuoteService::class);
    }

    public function test_new_subscription_quote_applies_term_discount(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $this->clearVendorMemberships($vendor);
        $plan = $this->basicPlan();

        $quote = $this->quoteService->quote($vendor, $plan, 3);

        $this->assertSame('new', $quote['purchase_type']);
        $this->assertSame(10000.0, $quote['monthly_price']);
        $this->assertSame(30000.0, $quote['subtotal']);
        $this->assertSame(15.0, $quote['discount_percent']);
        $this->assertSame(4500.0, $quote['discount_amount']);
        $this->assertSame(25500.0, $quote['gross_amount']);
        $this->assertSame(0.0, $quote['credit_amount']);
        $this->assertSame(25500.0, $quote['amount_due']);
    }

    public function test_extend_quote_charges_discounted_total_without_credit(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = $this->basicPlan();

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

        $this->assertSame('extend', $quote['purchase_type']);
        $this->assertSame(20.0, $quote['discount_percent']);
        $this->assertSame(48000.0, $quote['gross_amount']);
        $this->assertSame(0.0, $quote['credit_amount']);
        $this->assertSame(48000.0, $quote['amount_due']);
    }

    public function test_upgrade_quote_applies_remaining_subscription_credit(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $basic = $this->basicPlan();
        $premium = $this->premiumPlan();

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

        $this->assertSame('upgrade', $quote['purchase_type']);
        $this->assertSame(72000.0, $quote['gross_amount']);
        $this->assertSame(12750.0, $quote['credit_amount']);
        $this->assertSame(59250.0, $quote['amount_due']);
    }

    public function test_expired_subscription_is_quoted_as_new(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $this->clearVendorMemberships($vendor);
        $plan = $this->basicPlan();

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

        $this->assertSame('new', $quote['purchase_type']);
        $this->assertSame(10000.0, $quote['amount_due']);
        $this->assertNull($quote['current_membership']);
    }

    public function test_active_subscription_rejects_lower_tier_plan(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $basic = $this->basicPlan();
        $premium = $this->premiumPlan();

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
    }

    public function test_invalid_months_are_rejected(): void
    {
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $plan = $this->basicPlan();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->quoteService->quote($vendor, $plan, 2);
    }

    public function test_catalog_lists_active_term_discounts(): void
    {
        MembershipTermDiscount::query()->where('months', 6)->update(['is_active' => false]);

        $discounts = $this->quoteService->catalogTermDiscounts();

        $this->assertCount(3, $discounts);
        $this->assertSame([1, 3, 12], array_column($discounts, 'months'));
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

    private function premiumPlan(): MembershipPlan
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
}
