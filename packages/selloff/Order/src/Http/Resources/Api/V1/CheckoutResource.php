<?php

namespace App\Modules\Selloff\Order\Http\Resources\Api\V1;

use App\Modules\Selloff\Order\Models\CheckoutSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CheckoutSession */
class CheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checkout_token' => $this->checkout_token,
            'payment_method' => $this->payment_method,
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'grand_total' => $this->grand_total,
            'currency_code' => $this->currency_code,
            'coupon_code' => $this->coupon_code,
            'status' => $this->status,
            'transaction_number' => $this->transaction_number,
            'expires_at' => $this->expires_at,
            'items' => CheckoutItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
