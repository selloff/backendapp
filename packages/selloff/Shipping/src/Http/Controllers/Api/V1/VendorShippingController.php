<?php

namespace App\Modules\Selloff\Shipping\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Shipping\Models\DeliveryTimeOption;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Modules\Selloff\Shipping\Models\ShippingZone;
use App\Modules\Selloff\Shipping\Models\ShippingZoneLocation;
use App\Modules\Selloff\Shipping\Services\VendorShippingFormatter;
use App\Modules\Selloff\Shipping\Services\VendorShippingMethodNormalizer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorShippingController extends Controller
{
    public function __construct(
        private readonly VendorShippingFormatter $formatter,
        private readonly VendorShippingMethodNormalizer $methodNormalizer,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $sellerId = (int) $request->user()->id;

        $zones = ShippingZone::query()
            ->with(['methods', 'locations'])
            ->where('seller_id', $sellerId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (ShippingZone $zone) => $this->formatter->formatZone($zone));

        $deliveryTimes = DeliveryTimeOption::query()
            ->where('seller_id', $sellerId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DeliveryTimeOption $option) => $this->formatter->formatDeliveryTime($option));

        return ApiResponse::success([
            'zones' => $zones,
            'delivery_times' => $deliveryTimes,
        ]);
    }

    public function showZone(Request $request, ShippingZone $shippingZone): JsonResponse
    {
        $this->authorizeZone($request, $shippingZone);

        $shippingZone->load(['methods', 'locations']);

        return ApiResponse::success($this->formatter->formatZone($shippingZone));
    }

    public function storeZone(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $data = $this->validateZonePayload($request);

        $zone = DB::transaction(function () use ($request, $data) {
            $zone = ShippingZone::query()->create([
                'name' => $data['name'],
                'estimated_delivery' => $data['estimated_delivery'] ?? null,
                'seller_id' => $request->user()->id,
                'status' => $data['status'] ?? true,
            ]);

            $this->syncZoneLocations($zone, $data['regions'] ?? []);

            return $zone->fresh(['methods', 'locations']);
        });

        return ApiResponse::success($this->formatter->formatZone($zone), 201);
    }

    public function updateZone(Request $request, ShippingZone $shippingZone): JsonResponse
    {
        $this->authorizeZone($request, $shippingZone);

        $data = $this->validateZonePayload($request, partial: true);

        $zone = DB::transaction(function () use ($shippingZone, $data) {
            $updates = [];

            if (array_key_exists('name', $data)) {
                $updates['name'] = $data['name'];
            }
            if (array_key_exists('estimated_delivery', $data)) {
                $updates['estimated_delivery'] = $data['estimated_delivery'];
            }
            if (array_key_exists('status', $data)) {
                $updates['status'] = $data['status'];
            }

            if ($updates !== []) {
                $shippingZone->update($updates);
            }

            if (array_key_exists('regions', $data)) {
                $this->syncZoneLocations($shippingZone, $data['regions']);
            }

            return $shippingZone->fresh(['methods', 'locations']);
        });

        return ApiResponse::success($this->formatter->formatZone($zone));
    }

    public function destroyZone(Request $request, ShippingZone $shippingZone): JsonResponse
    {
        $this->authorizeZone($request, $shippingZone);

        DB::transaction(function () use ($shippingZone) {
            $shippingZone->locations()->delete();
            $shippingZone->methods()->delete();
            $shippingZone->delete();
        });

        return ApiResponse::success(message: 'Shipping zone deleted.');
    }

    public function storeMethod(Request $request, ShippingZone $shippingZone): JsonResponse
    {
        $this->authorizeZone($request, $shippingZone);

        $data = $request->validate([
            'method_type' => ['nullable', 'string', 'in:flat_rate,local_pickup,free_shipping'],
            'name' => ['nullable', 'string', 'max:255'],
            'flat_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['method_type'])) {
            $attributes = $this->methodNormalizer->defaultAttributesForType(
                $this->methodNormalizer->normalizeMethodType($data['method_type']),
            );
        } else {
            $attributes = [
                'name' => $data['name'] ?? 'Flat Rate',
                'method_type' => 'flat_rate',
                'cost_calculation_type' => 'per_order',
                'shipping_flat_cost' => $data['flat_rate'] ?? 0,
                'flat_rate' => $data['flat_rate'] ?? 0,
            ];
        }

        if (! empty($data['name'])) {
            $attributes['name'] = $data['name'];
        }

        $method = ShippingMethod::query()->create([
            ...$attributes,
            'shipping_zone_id' => $shippingZone->id,
            'status' => $data['status'] ?? true,
        ]);

        return ApiResponse::success($this->formatter->formatMethod($method), 201);
    }

    public function updateMethod(Request $request, ShippingZone $shippingZone, ShippingMethod $shippingMethod): JsonResponse
    {
        $this->authorizeZone($request, $shippingZone);
        abort_unless((int) $shippingMethod->shipping_zone_id === (int) $shippingZone->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'flat_rate' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['nullable', 'boolean'],
            'free_shipping_min_amount' => ['sometimes', 'numeric', 'min:0'],
            'local_pickup_cost' => ['sometimes', 'numeric', 'min:0'],
            'cost_calculation_type' => ['sometimes', 'string', 'in:total_weight,per_order,per_item'],
            'shipping_flat_cost' => ['sometimes', 'numeric', 'min:0'],
            'flat_rate_costs' => ['sometimes', 'array'],
            'flat_rate_costs.*.min_weight' => ['nullable', 'numeric', 'min:0'],
            'flat_rate_costs.*.max_weight' => ['nullable', 'numeric', 'min:0'],
            'flat_rate_costs.*.cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payload = $this->methodNormalizer->normalizeUpdatePayload($shippingMethod, $data);
        $shippingMethod->update($payload);

        return ApiResponse::success($this->formatter->formatMethod($shippingMethod->fresh()));
    }

    public function destroyMethod(Request $request, ShippingZone $shippingZone, ShippingMethod $shippingMethod): JsonResponse
    {
        $this->authorizeZone($request, $shippingZone);
        abort_unless((int) $shippingMethod->shipping_zone_id === (int) $shippingZone->id, 404);

        $shippingMethod->delete();

        return ApiResponse::success(message: 'Shipping method deleted.');
    }

    public function storeDeliveryTime(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]);

        $option = DeliveryTimeOption::query()->create([
            'seller_id' => $request->user()->id,
            'label' => $data['label'],
            'status' => $data['status'] ?? true,
        ]);

        return ApiResponse::success($this->formatter->formatDeliveryTime($option), 201);
    }

    public function updateDeliveryTime(Request $request, DeliveryTimeOption $deliveryTimeOption): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);
        abort_unless((int) $deliveryTimeOption->seller_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]);

        $deliveryTimeOption->update($data);

        return ApiResponse::success($this->formatter->formatDeliveryTime($deliveryTimeOption->fresh()));
    }

    public function destroyDeliveryTime(Request $request, DeliveryTimeOption $deliveryTimeOption): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);
        abort_unless((int) $deliveryTimeOption->seller_id === (int) $request->user()->id, 403);

        $deliveryTimeOption->delete();

        return ApiResponse::success(message: 'Delivery time deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateZonePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'estimated_delivery' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'boolean'],
            'regions' => ['nullable', 'array'],
            'regions.*.country_id' => ['nullable', 'integer', 'min:1'],
            'regions.*.state_id' => ['nullable', 'integer', 'min:1'],
        ];

        return $request->validate($rules);
    }

    /**
     * @param  list<array{country_id?: int|null, state_id?: int|null}>  $regions
     */
    private function syncZoneLocations(ShippingZone $zone, array $regions): void
    {
        $zone->locations()->delete();

        foreach ($regions as $region) {
            $countryId = isset($region['country_id']) ? (int) $region['country_id'] : null;
            $stateId = isset($region['state_id']) ? (int) $region['state_id'] : null;

            if (! $countryId && ! $stateId) {
                continue;
            }

            ShippingZoneLocation::query()->create([
                'shipping_zone_id' => $zone->id,
                'country_id' => $countryId ?: null,
                'state_id' => $stateId ?: null,
            ]);
        }
    }

    private function authorizeZone(Request $request, ShippingZone $shippingZone): void
    {
        abort_unless($request->user()->can('vendor'), 403);
        abort_unless((int) $shippingZone->seller_id === (int) $request->user()->id, 403);
    }
}
