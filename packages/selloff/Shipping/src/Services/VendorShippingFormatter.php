<?php

namespace App\Modules\Selloff\Shipping\Services;

use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Location\Models\State;
use App\Modules\Selloff\Shipping\Models\DeliveryTimeOption;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Modules\Selloff\Shipping\Models\ShippingZone;
use App\Modules\Selloff\Shipping\Models\ShippingZoneLocation;

class VendorShippingFormatter
{
    /**
     * @return array<string, mixed>
     */
    public function formatZone(ShippingZone $zone): array
    {
        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'status' => (bool) $zone->status,
            'estimated_delivery' => $zone->estimated_delivery,
            'regions' => $zone->locations
                ->map(fn (ShippingZoneLocation $location) => [
                    'id' => $location->id,
                    'country_id' => $location->country_id,
                    'state_id' => $location->state_id,
                    'label' => $this->locationLabel($location),
                ])
                ->values()
                ->all(),
            'methods' => $zone->methods
                ->map(fn (ShippingMethod $method) => $this->formatMethod($method))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatMethod(ShippingMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'method_type' => $method->method_type ?: 'flat_rate',
            'flat_rate' => $method->flat_rate,
            'status' => (bool) $method->status,
            'free_shipping_min_amount' => $method->free_shipping_min_amount,
            'local_pickup_cost' => $method->local_pickup_cost,
            'cost_calculation_type' => $method->cost_calculation_type,
            'shipping_flat_cost' => $method->shipping_flat_cost,
            'flat_rate_costs' => $method->flat_rate_costs ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDeliveryTime(DeliveryTimeOption $option): array
    {
        return [
            'id' => $option->id,
            'label' => $option->label ?? $option->option_key ?? '',
            'status' => (bool) $option->status,
        ];
    }

    public function locationLabel(ShippingZoneLocation $location): string
    {
        $countryName = $location->country_id
            ? Country::query()->where('id', $location->country_id)->value('name')
            : null;
        $stateName = $location->state_id
            ? State::query()->where('id', $location->state_id)->value('name')
            : null;

        if ($countryName && $stateName) {
            return $countryName.'/'.$stateName;
        }

        if ($countryName) {
            return (string) $countryName;
        }

        return 'All regions';
    }
}
