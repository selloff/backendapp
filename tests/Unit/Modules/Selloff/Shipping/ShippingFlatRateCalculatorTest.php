<?php

use App\Modules\Selloff\Shipping\Services\ShippingFlatRateCalculator;

test('calculates per order cost', function () {
    $calculator = new ShippingFlatRateCalculator();

    expect($calculator->calculate('per_order', 0, 0, 1500, null))->toBe(1500.0);
});

test('calculates per item cost', function () {
    $calculator = new ShippingFlatRateCalculator();

    expect($calculator->calculate('per_item', 0, 3, 1000, null))->toBe(3000.0);
});

test('calculates total weight cost from matching tier', function () {
    $calculator = new ShippingFlatRateCalculator();

    $cost = $calculator->calculate('total_weight', 2.5, 1, null, [
        ['min_weight' => 0, 'max_weight' => 1, 'cost' => 500],
        ['min_weight' => 1, 'max_weight' => 5, 'cost' => 1200],
    ]);

    expect($cost)->toBe(1200.0);
});
