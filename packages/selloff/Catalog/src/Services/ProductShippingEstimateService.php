<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductShippingEstimateService
{
    /**
     * @return array{status: string, label: ?string, delivery_time_label: ?string, location_label: ?string}
     */
    public function estimate(Product $product, ?int $countryId, ?int $stateId): array
    {
        $deliveryTimeLabel = null;
        if ($product->delivery_time_option_id) {
            $deliveryTimeLabel = DB::table('delivery_time_options')
                ->where('id', $product->delivery_time_option_id)
                ->value('label');
        }

        if (! $countryId || ! $stateId) {
            return [
                'status' => 'location_required',
                'label' => null,
                'delivery_time_label' => $deliveryTimeLabel,
                'location_label' => null,
            ];
        }

        $locations = DB::table('shipping_zone_locations as szl')
            ->join('shipping_zones as sz', 'sz.id', '=', 'szl.shipping_zone_id')
            ->where('sz.seller_id', $product->vendor_id)
            ->where('sz.status', true)
            ->where(function ($query) use ($countryId, $stateId): void {
                $query->where(function ($inner) use ($countryId, $stateId): void {
                    $inner->where('szl.country_id', $countryId)->where('szl.state_id', $stateId);
                })->orWhere('szl.country_id', $countryId);
            })
            ->select(['szl.country_id', 'szl.state_id', 'sz.estimated_delivery'])
            ->get();

        $label = null;
        foreach ($locations as $location) {
            if ((int) $location->country_id === $countryId && (int) $location->state_id === $stateId) {
                $label = (string) $location->estimated_delivery;
                break;
            }
        }

        if ($label === null) {
            foreach ($locations as $location) {
                if ((int) $location->country_id === $countryId) {
                    $label = (string) $location->estimated_delivery;
                    break;
                }
            }
        }

        if ($label === null || trim($label) === '') {
            return [
                'status' => 'no_delivery',
                'label' => 'Delivery is not available to this location',
                'delivery_time_label' => $deliveryTimeLabel,
                'location_label' => $this->locationLabel($countryId, $stateId),
            ];
        }

        return [
            'status' => 'ok',
            'label' => $label,
            'delivery_time_label' => $deliveryTimeLabel,
            'location_label' => $this->locationLabel($countryId, $stateId),
        ];
    }

    private function locationLabel(int $countryId, int $stateId): ?string
    {
        $state = DB::table('states')->where('id', $stateId)->value('name');
        $country = DB::table('countries')->where('id', $countryId)->value('name');

        if ($state && $country) {
            return trim("{$state}, {$country}");
        }

        return $state ? (string) $state : ($country ? (string) $country : null);
    }
}
