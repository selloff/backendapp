<?php

namespace Tests\Unit\LegacyImport;

use App\LegacyImport\Support\MembershipLegacyImportMapper;
use Tests\TestCase;

class MembershipLegacyImportMapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true]);
    }

    public function test_term_months_from_plan_title(): void
    {
        $this->assertSame(1, MembershipLegacyImportMapper::termMonthsFromPlanTitle('Bronze Membership (Number of Ads: 50, Number of Days: 30)'));
        $this->assertSame(2, MembershipLegacyImportMapper::termMonthsFromPlanTitle('Bronze Membership (Number of Ads: 50, Number of Days: 60)'));
    }

    public function test_transaction_parity_columns_populate_checkout_breakdown(): void
    {
        $columns = MembershipLegacyImportMapper::transactionParityColumns([
            'payment_amount' => '8500.00',
            'plan_title' => 'Silver Membership (Number of Ads: 50, Number of Days: 30)',
        ], 30);

        $this->assertSame(1, $columns['term_months'] ?? null);
        $this->assertSame('new', $columns['purchase_type'] ?? null);
        $this->assertSame(8500.0, (float) ($columns['amount_charged'] ?? 0));
        $this->assertSame(8500.0, (float) ($columns['gross_amount'] ?? 0));
    }

    public function test_subscription_parity_columns_populate_last_paid_amount(): void
    {
        $columns = MembershipLegacyImportMapper::subscriptionParityColumns([
            'number_of_days' => 30,
            'price' => '4500.00',
            'is_free' => 0,
            'payment_status' => 'payment_received',
        ]);

        $this->assertSame(1, $columns['term_months'] ?? null);
        $this->assertSame(4500.0, (float) ($columns['last_paid_amount'] ?? 0));
    }
}
