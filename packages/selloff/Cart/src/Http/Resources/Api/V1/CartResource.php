<?php

namespace App\Modules\Selloff\Cart\Http\Resources\Api\V1;

use App\Modules\Selloff\Affiliate\Services\AffiliateAttributionService;
use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Cart\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cart */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $affiliateLinkId = app(AffiliateAttributionService::class)->linkIdFromRequest($request);
        $totals = app(CartService::class)->calculateTotals($this->resource, null, $affiliateLinkId);
        $sellers = $this->sellerSummary();

        return [
            'id' => $this->id,
            'currency_code' => $this->currency_code,
            'coupon_code' => $this->coupon_code,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'totals' => $totals,
            'seller_count' => count($sellers),
            'has_multiple_sellers' => count($sellers) > 1,
            'sellers' => $sellers,
        ];
    }

    /**
     * @return list<array{
     *     seller_id: int,
     *     item_count: int,
     *     seller: array{id: int, username: string|null, slug: string|null, shop_name: string|null}|null
     * }>
     */
    private function sellerSummary(): array
    {
        if (! $this->relationLoaded('items')) {
            return [];
        }

        $groups = [];

        foreach ($this->items as $item) {
            $sellerId = (int) $item->seller_id;

            if (! isset($groups[$sellerId])) {
                $groups[$sellerId] = [
                    'seller_id' => $sellerId,
                    'item_count' => 0,
                    'seller' => null,
                ];

                if ($item->relationLoaded('seller') && $item->seller) {
                    $groups[$sellerId]['seller'] = [
                        'id' => $item->seller->id,
                        'username' => $item->seller->username ?? $item->seller->slug,
                        'slug' => $item->seller->slug,
                        'shop_name' => $item->seller->vendorProfile?->shop_name,
                    ];
                }
            }

            $groups[$sellerId]['item_count'] += (int) $item->quantity;
        }

        return array_values($groups);
    }
}
