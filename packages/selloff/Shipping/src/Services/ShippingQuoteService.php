<?php

namespace App\Modules\Selloff\Shipping\Services;

use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Shipping\Models\ShippingMethod;
use App\Modules\Selloff\Shipping\Models\ShippingZone;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ShippingQuoteService
{
    public function __construct(
        private readonly CartShippingMetrics $metrics,
        private readonly ShippingMethodCostResolver $costResolver,
    ) {}

    /**
     * @return Collection<int, ShippingMethod>
     */
    public function quote(?int $countryId, ?int $stateId, ?int $sellerId = null): Collection
    {
        $query = ShippingZone::query()
            ->with(['methods' => fn ($q) => $q->where('status', true)])
            ->where('status', true);

        if ($sellerId) {
            $query->where(function ($q) use ($sellerId) {
                $q->whereNull('seller_id')->orWhere('seller_id', $sellerId);
            });
        }

        if ($countryId || $stateId) {
            $query->whereHas('locations', function ($q) use ($countryId, $stateId) {
                if ($stateId) {
                    $q->where('state_id', $stateId);
                } elseif ($countryId) {
                    $q->where('country_id', $countryId);
                }
            });
        }

        return $query->get()->flatMap(fn (ShippingZone $zone) => $zone->methods)->values();
    }

    /**
     * @return list<array{
     *     seller_id: int,
     *     seller: array{id: int, username: string|null, slug: string|null, shop_name: string|null}|null,
     *     methods: list<array{
     *         id: int,
     *         name: string,
     *         method_type: string,
     *         flat_rate: string|float,
     *         shipping_zone_id: int
     *     }>
     * }>
     */
    public function quoteForCart(Cart $cart, ?int $countryId = null, ?int $stateId = null): array
    {
        $cart->loadMissing(['items.product', 'items.seller.vendorProfile']);
        $groups = [];

        foreach ($cart->items as $item) {
            $sellerId = (int) $item->seller_id;

            if (isset($groups[$sellerId])) {
                continue;
            }

            $methods = $this->quote($countryId, $stateId, $sellerId)
                ->map(function (ShippingMethod $method) use ($cart, $sellerId): ?array {
                    $cost = $this->resolveMethodCost($method, $cart, $sellerId);

                    if ($cost === null) {
                        return null;
                    }

                    return $this->formatQuotedMethod($method, $cost);
                })
                ->filter()
                ->sortBy(fn (array $method) => match ($method['method_type']) {
                    'free_shipping' => 1,
                    'local_pickup' => 2,
                    default => 3,
                })
                ->values()
                ->all();

            $groups[$sellerId] = [
                'seller_id' => $sellerId,
                'seller' => $item->seller ? [
                    'id' => $item->seller->id,
                    'username' => $item->seller->username ?? $item->seller->slug,
                    'slug' => $item->seller->slug,
                    'shop_name' => $item->seller->vendorProfile?->shop_name,
                ] : null,
                'methods' => $methods,
            ];
        }

        return array_values($groups);
    }

    /**
     * @param  list<array{seller_id: int, shipping_method_id: int}>  $sellerShipping
     */
    public function applyPerSellerToCart(
        Cart $cart,
        array $sellerShipping,
        ?int $countryId = null,
        ?int $stateId = null,
    ): Cart {
        $cart->loadMissing(['items.product']);
        $sellerIds = $cart->items->pluck('seller_id')->unique()->map(fn ($id) => (int) $id)->all();
        $selections = collect($sellerShipping)->keyBy('seller_id');

        foreach ($sellerIds as $sellerId) {
            if (! $selections->has($sellerId)) {
                throw ValidationException::withMessages([
                    'seller_shipping' => ["Select a shipping method for seller #{$sellerId}."],
                ]);
            }
        }

        $totalShipping = 0.0;
        $perSellerData = [];

        foreach ($sellerShipping as $row) {
            $sellerId = (int) $row['seller_id'];
            $method = ShippingMethod::query()->with('zone')->findOrFail($row['shipping_method_id']);

            $this->assertMethodAvailableForSeller($method, $sellerId);

            if (! $method->status) {
                throw ValidationException::withMessages([
                    'seller_shipping' => ['One or more shipping methods are unavailable.'],
                ]);
            }

            $cost = $this->resolveMethodCost($method, $cart, $sellerId);

            if ($cost === null) {
                throw ValidationException::withMessages([
                    'seller_shipping' => ['One or more shipping methods are unavailable for this cart.'],
                ]);
            }

            $totalShipping += $cost;
            $perSellerData[] = [
                'seller_id' => $sellerId,
                'shipping_method_id' => $method->id,
                'shipping_method_name' => $method->name,
                'shipping_method_type' => $method->method_type ?: 'flat_rate',
                'flat_rate' => $cost,
                'shipping_zone_id' => $method->shipping_zone_id,
            ];
        }

        $cart->update([
            'shipping_cost' => $totalShipping,
            'country_id' => $countryId,
            'state_id' => $stateId,
            'shipping_data' => [
                'per_seller' => $perSellerData,
                'country_id' => $countryId,
                'state_id' => $stateId,
            ],
            'shipping_cost_data' => [
                'per_seller' => $perSellerData,
                'total' => $totalShipping,
            ],
        ]);

        return $cart->fresh()->load(['items.product', 'items.seller']);
    }

    public function applyToCart(Cart $cart, int $shippingMethodId, ?int $countryId = null, ?int $stateId = null): Cart
    {
        $method = ShippingMethod::query()->with('zone')->findOrFail($shippingMethodId);

        if (! $method->status) {
            throw ValidationException::withMessages([
                'shipping_method_id' => ['Shipping method is unavailable.'],
            ]);
        }

        $cart->loadMissing(['items.product']);
        $sellerId = (int) ($method->zone?->seller_id ?? $cart->items->first()?->seller_id ?? 0);

        if ($cart->items->isNotEmpty()) {
            if ($sellerId <= 0) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => ['Shipping method is unavailable.'],
                ]);
            }

            $this->assertMethodAvailableForSeller($method, $sellerId);
        }

        $cost = $this->resolveMethodCost($method, $cart, $sellerId);

        if ($cost === null) {
            throw ValidationException::withMessages([
                'shipping_method_id' => ['Shipping method is unavailable for this cart.'],
            ]);
        }

        $cart->update([
            'shipping_cost' => $cost,
            'country_id' => $countryId,
            'state_id' => $stateId,
            'shipping_data' => [
                'shipping_method_id' => $method->id,
                'shipping_method_name' => $method->name,
                'shipping_method_type' => $method->method_type ?: 'flat_rate',
                'shipping_zone_id' => $method->shipping_zone_id,
                'country_id' => $countryId,
                'state_id' => $stateId,
            ],
            'shipping_cost_data' => [
                'flat_rate' => $cost,
            ],
        ]);

        return $cart->fresh()->load(['items.product', 'items.seller']);
    }

    public function resolveMethodCost(ShippingMethod $method, Cart $cart, int $sellerId): ?float
    {
        return $this->costResolver->resolve(
            $method,
            $this->metrics->sellerChargeableWeight($cart, $sellerId),
            $this->metrics->sellerItemCount($cart, $sellerId),
            $this->metrics->sellerPhysicalSubtotal($cart, $sellerId),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     method_type: string,
     *     flat_rate: float,
     *     shipping_zone_id: int
     * }
     */
    private function formatQuotedMethod(ShippingMethod $method, float $cost): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'method_type' => $method->method_type ?: 'flat_rate',
            'flat_rate' => $cost,
            'shipping_zone_id' => $method->shipping_zone_id,
        ];
    }

    private function assertMethodAvailableForSeller(ShippingMethod $method, int $sellerId): void
    {
        $zoneSellerId = (int) ($method->zone?->seller_id ?? 0);

        if ($zoneSellerId > 0 && $zoneSellerId !== $sellerId) {
            throw ValidationException::withMessages([
                'seller_shipping' => ['One or more shipping methods do not belong to the selected seller.'],
            ]);
        }
    }
}
