<?php

namespace Tests\Unit\Modules\Selloff\Shipping;

use App\Modules\Selloff\Shipping\Services\ShippingFlatRateCalculator;
use PHPUnit\Framework\TestCase;

class ShippingFlatRateCalculatorTest extends TestCase
{
    public function test_calculates_per_order_cost(): void
    {
        $calculator = new ShippingFlatRateCalculator();

        $this->assertSame(1500.0, $calculator->calculate('per_order', 0, 0, 1500, null));
    }

    public function test_calculates_per_item_cost(): void
    {
        $calculator = new ShippingFlatRateCalculator();

        $this->assertSame(3000.0, $calculator->calculate('per_item', 0, 3, 1000, null));
    }

    public function test_calculates_total_weight_cost_from_matching_tier(): void
    {
        $calculator = new ShippingFlatRateCalculator();

        $cost = $calculator->calculate('total_weight', 2.5, 1, null, [
            ['min_weight' => 0, 'max_weight' => 1, 'cost' => 500],
            ['min_weight' => 1, 'max_weight' => 5, 'cost' => 1200],
        ]);

        $this->assertSame(1200.0, $cost);
    }
}
