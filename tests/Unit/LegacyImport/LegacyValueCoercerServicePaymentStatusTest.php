<?php

use App\LegacyImport\Support\LegacyValueCoercer;

test('service payment status normalizes legacy values', function () {
    expect(LegacyValueCoercer::servicePaymentStatus('success'))->toBe('completed');
    expect(LegacyValueCoercer::servicePaymentStatus('Paid'))->toBe('completed');
    expect(LegacyValueCoercer::servicePaymentStatus('payment_received'))->toBe('completed');
    expect(LegacyValueCoercer::servicePaymentStatus('pending_payment'))->toBe('pending');
});
