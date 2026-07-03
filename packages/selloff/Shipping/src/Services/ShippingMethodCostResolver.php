<?php

namespace App\Modules\Selloff\Shipping\Services;

use App\Modules\Selloff\Shipping\Models\ShippingMethod;

class ShippingMethodCostResolver
{
    public function __construct(
        private readonly ShippingFlatRateCalculator $flatRateCalculator,
    ) {}

    public function resolve(
        ShippingMethod $method,
        float $totalWeight,
        int $itemCount,
        float $sellerSubtotal,
    ): ?float {
        $methodType = $method->method_type ?: 'flat_rate';

        return match ($methodType) {
            'free_shipping' => $this->resolveFreeShipping($method, $sellerSubtotal),
            'local_pickup' => $this->resolveLocalPickup($method),
            'flat_rate' => $this->resolveFlatRate($method, $totalWeight, $itemCount),
            default => is_numeric($method->flat_rate) ? (float) $method->flat_rate : null,
        };
    }

    public function sortOrder(ShippingMethod $method): int
    {
        return match ($method->method_type ?: 'flat_rate') {
            'free_shipping' => 1,
            'local_pickup' => 2,
            default => 3,
        };
    }

    private function resolveFreeShipping(ShippingMethod $method, float $sellerSubtotal): ?float
    {
        $minimum = (float) ($method->free_shipping_min_amount ?? 0);

        return $sellerSubtotal >= $minimum ? 0.0 : null;
    }

    private function resolveLocalPickup(ShippingMethod $method): ?float
    {
        return is_numeric($method->local_pickup_cost) ? (float) $method->local_pickup_cost : null;
    }

    private function resolveFlatRate(ShippingMethod $method, float $totalWeight, int $itemCount): ?float
    {
        $calculationType = $method->cost_calculation_type ?: 'total_weight';

        if (! in_array($calculationType, ['total_weight', 'per_order', 'per_item'], true)) {
            return null;
        }

        $cost = $this->flatRateCalculator->calculate(
            $calculationType,
            $totalWeight,
            $itemCount,
            is_numeric($method->shipping_flat_cost) ? (float) $method->shipping_flat_cost : null,
            $method->flat_rate_costs,
        );

        if ($cost !== null) {
            return $cost;
        }

        return is_numeric($method->flat_rate) ? (float) $method->flat_rate : null;
    }
}
