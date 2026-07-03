<?php

namespace App\Modules\Selloff\Shipping\Services;

use App\Modules\Selloff\Shipping\Models\ShippingMethod;

class VendorShippingMethodNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function defaultAttributesForType(string $methodType): array
    {
        return match ($methodType) {
            'local_pickup' => [
                'name' => 'Local Pickup',
                'method_type' => 'local_pickup',
                'local_pickup_cost' => 0,
                'flat_rate' => 0,
            ],
            'free_shipping' => [
                'name' => 'Free Shipping',
                'method_type' => 'free_shipping',
                'free_shipping_min_amount' => 0,
                'flat_rate' => 0,
            ],
            default => [
                'name' => 'Flat Rate',
                'method_type' => 'flat_rate',
                'cost_calculation_type' => 'total_weight',
                'flat_rate_costs' => [
                    ['min_weight' => 0, 'max_weight' => null, 'cost' => 0],
                ],
                'shipping_flat_cost' => 0,
                'flat_rate' => 0,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalizeUpdatePayload(ShippingMethod $method, array $data): array
    {
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = $data['name'];
        }

        if (array_key_exists('status', $data)) {
            $payload['status'] = (bool) $data['status'];
        }

        $methodType = $method->method_type ?: 'flat_rate';

        if ($methodType === 'free_shipping' && array_key_exists('free_shipping_min_amount', $data)) {
            $payload['free_shipping_min_amount'] = $data['free_shipping_min_amount'];
        }

        if ($methodType === 'local_pickup' && array_key_exists('local_pickup_cost', $data)) {
            $payload['local_pickup_cost'] = $data['local_pickup_cost'];
        }

        if ($methodType === 'flat_rate') {
            if (array_key_exists('cost_calculation_type', $data)) {
                $payload['cost_calculation_type'] = $this->normalizeCalculationType($data['cost_calculation_type']);
            }

            if (array_key_exists('shipping_flat_cost', $data)) {
                $payload['shipping_flat_cost'] = $data['shipping_flat_cost'];
            }

            if (array_key_exists('flat_rate_costs', $data)) {
                $payload['flat_rate_costs'] = $this->normalizeFlatRateCosts($data['flat_rate_costs']);
            }
        }

        if (array_key_exists('flat_rate', $data)) {
            $payload['flat_rate'] = $data['flat_rate'];
        } else {
            $merged = array_merge($method->toArray(), $payload);
            $payload['flat_rate'] = $this->resolveDisplayFlatRate($merged);
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>|null  $rates
     * @return list<array{min_weight: float, max_weight: float|null, cost: float}>
     */
    public function normalizeFlatRateCosts(?array $rates): array
    {
        if ($rates === null) {
            return [];
        }

        $normalized = [];

        foreach ($rates as $rate) {
            if (! is_array($rate) || ! isset($rate['cost']) || $rate['cost'] === '' || ! is_numeric($rate['cost'])) {
                continue;
            }

            $normalized[] = [
                'min_weight' => isset($rate['min_weight']) && is_numeric($rate['min_weight'])
                    ? (float) $rate['min_weight']
                    : 0.0,
                'max_weight' => isset($rate['max_weight']) && $rate['max_weight'] !== '' && is_numeric($rate['max_weight'])
                    ? (float) $rate['max_weight']
                    : null,
                'cost' => (float) $rate['cost'],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resolveDisplayFlatRate(array $attributes): float
    {
        $methodType = $attributes['method_type'] ?? 'flat_rate';

        return match ($methodType) {
            'free_shipping' => 0.0,
            'local_pickup' => (float) ($attributes['local_pickup_cost'] ?? 0),
            'flat_rate' => match ($attributes['cost_calculation_type'] ?? null) {
                'per_order', 'per_item' => (float) ($attributes['shipping_flat_cost'] ?? 0),
                default => (float) (($attributes['flat_rate_costs'][0]['cost'] ?? null) ?? ($attributes['flat_rate'] ?? 0)),
            },
            default => (float) ($attributes['flat_rate'] ?? 0),
        };
    }

    public function normalizeCalculationType(mixed $value): string
    {
        return in_array($value, ['total_weight', 'per_order', 'per_item'], true)
            ? (string) $value
            : 'total_weight';
    }

    public function normalizeMethodType(mixed $value): string
    {
        return in_array($value, ['flat_rate', 'local_pickup', 'free_shipping'], true)
            ? (string) $value
            : 'flat_rate';
    }
}
