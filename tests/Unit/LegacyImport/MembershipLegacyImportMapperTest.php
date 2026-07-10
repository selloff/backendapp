<?php

use App\LegacyImport\Support\MembershipLegacyImportMapper;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true]);
});

test('term months from plan title', function () {
    expect(MembershipLegacyImportMapper::termMonthsFromPlanTitle('Bronze Membership (Number of Ads: 50, Number of Days: 30)'))->toBe(1);
    expect(MembershipLegacyImportMapper::termMonthsFromPlanTitle('Bronze Membership (Number of Ads: 50, Number of Days: 60)'))->toBe(2);
});

test('transaction parity columns populate checkout breakdown', function () {
    $columns = MembershipLegacyImportMapper::transactionParityColumns([
        'payment_amount' => '8500.00',
        'plan_title' => 'Silver Membership (Number of Ads: 50, Number of Days: 30)',
    ], 30);

    expect($columns['term_months'] ?? null)->toBe(1);
    expect($columns['purchase_type'] ?? null)->toBe('new');
    expect((float) ($columns['amount_charged'] ?? 0))->toBe(8500.0);
    expect((float) ($columns['gross_amount'] ?? 0))->toBe(8500.0);
});

test('subscription parity columns populate last paid amount', function () {
    $columns = MembershipLegacyImportMapper::subscriptionParityColumns([
        'number_of_days' => 30,
        'price' => '4500.00',
        'is_free' => 0,
        'payment_status' => 'payment_received',
    ]);

    expect($columns['term_months'] ?? null)->toBe(1);
    expect((float) ($columns['last_paid_amount'] ?? 0))->toBe(4500.0);
});
