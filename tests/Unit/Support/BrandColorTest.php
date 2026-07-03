<?php

namespace Tests\Unit\Support;

use App\Support\BrandColor;
use Tests\TestCase;

class BrandColorTest extends TestCase
{
    public function test_normalizes_legacy_default_token(): void
    {
        $this->assertSame(BrandColor::DEFAULT_PRIMARY, BrandColor::normalize('default'));
    }

    public function test_keeps_valid_hex(): void
    {
        $this->assertSame('#ff5500', BrandColor::normalize('#ff5500'));
    }

    public function test_rejects_invalid_values(): void
    {
        $this->assertSame(BrandColor::DEFAULT_PRIMARY, BrandColor::normalize('0'));
        $this->assertSame(BrandColor::DEFAULT_PRIMARY, BrandColor::normalize('not-a-color'));
    }

    public function test_rejects_achromatic_legacy_grey(): void
    {
        $this->assertSame(BrandColor::DEFAULT_PRIMARY, BrandColor::normalize('#222222'));
        $this->assertSame(BrandColor::DEFAULT_PRIMARY, BrandColor::normalize('#1C1C1C'));
    }
}
