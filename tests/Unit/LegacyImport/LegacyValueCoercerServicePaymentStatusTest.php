<?php

namespace Tests\Unit\LegacyImport;

use App\LegacyImport\Support\LegacyValueCoercer;
use Tests\TestCase;

class LegacyValueCoercerServicePaymentStatusTest extends TestCase
{
    public function test_service_payment_status_normalizes_legacy_values(): void
    {
        $this->assertSame('completed', LegacyValueCoercer::servicePaymentStatus('success'));
        $this->assertSame('completed', LegacyValueCoercer::servicePaymentStatus('Paid'));
        $this->assertSame('completed', LegacyValueCoercer::servicePaymentStatus('payment_received'));
        $this->assertSame('pending', LegacyValueCoercer::servicePaymentStatus('pending_payment'));
    }
}
