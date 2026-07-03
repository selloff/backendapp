<?php

namespace App\Modules\Selloff\Shipping\Services;

class ShippingFlatRateCalculator
{
    /**
     * @param  list<array{min_weight?: mixed, max_weight?: mixed, cost?: mixed}>|null  $rates
     */
    public function calculate(
        string $calculationType,
        float $totalWeight,
        int $totalItems,
        ?float $flatCost,
        ?array $rates,
    ): ?float {
        return match ($calculationType) {
            'per_order' => is_numeric($flatCost) ? (float) $flatCost : null,
            'per_item' => is_numeric($flatCost) && $totalItems > 0
                ? (float) $flatCost * $totalItems
                : null,
            'total_weight' => $this->calculateByWeight($totalWeight, $rates),
            default => null,
        };
    }

    /**
     * @param  list<array{min_weight?: mixed, max_weight?: mixed, cost?: mixed}>|null  $rates
     */
    private function calculateByWeight(float $totalWeight, ?array $rates): ?float
    {
        if ($rates === null || $rates === []) {
            return null;
        }

        foreach ($rates as $rate) {
            if (! isset($rate['min_weight'], $rate['cost']) || ! is_numeric($rate['min_weight']) || ! is_numeric($rate['cost'])) {
                continue;
            }

            $minWeight = (float) $rate['min_weight'];
            $cost = (float) $rate['cost'];
            $maxWeight = (! isset($rate['max_weight']) || $rate['max_weight'] === '' || $rate['max_weight'] === null)
                ? PHP_FLOAT_MAX
                : (float) $rate['max_weight'];

            if ($totalWeight >= $minWeight && $totalWeight <= $maxWeight) {
                return $cost;
            }
        }

        return null;
    }
}
