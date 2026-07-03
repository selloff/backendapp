<?php

namespace App\Modules\Selloff\Order\Http\Resources\Api\V1;

use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Services\BuyerCancelOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canCancel = false;
        if ($request->user() && (int) $this->buyer_id === (int) $request->user()->id) {
            $canCancel = app(BuyerCancelOrderService::class)->canCancel($this->resource, $request->user());
        }

        if ($this->payment_status === 'payment_received') {
            $request->attributes->set('expose_digital_downloads', true);
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'guest_email' => $this->guest_email,
            'is_guest' => $this->buyer_id === null,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'price_subtotal' => $this->price_subtotal,
            'price_vat' => $this->price_vat,
            'price_shipping' => $this->price_shipping,
            'price_total' => $this->price_total,
            'currency_code' => $this->currency_code,
            'coupon_code' => $this->coupon_code,
            'coupon_discount' => $this->coupon_discount,
            'coupon_discount_rate' => $this->coupon_discount_rate,
            'global_taxes_data' => $this->global_taxes_data,
            'affiliate_data' => $this->affiliate_data,
            'clear_affiliate_cookie' => $this->shouldClearAffiliateCookie(),
            'transaction_fee' => $this->transaction_fee,
            'transaction_fee_rate' => $this->transaction_fee_rate,
            'transaction_id' => $this->transaction_id,
            'invoice_number' => $this->payment_status === 'payment_received' ? 'INV-'.$this->order_number : null,
            'can_cancel' => $canCancel,
            'shipping_tracking_number' => $this->shipping_snapshot['tracking_number'] ?? null,
            'shipping_snapshot' => $this->when(
                $request->user() && ((int) $this->buyer_id === (int) $request->user()->id || $request->user()->can('admin_panel')),
                $this->shipping_snapshot,
            ),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'buyer' => $this->whenLoaded('buyer', fn () => $this->buyer ? [
                'id' => $this->buyer->id,
                'name' => $this->buyer->name,
                'username' => $this->buyer->username,
                'slug' => $this->buyer->slug,
                'email' => $this->buyer->email,
                'phone_number' => $this->buyer->phone_number,
                'avatar' => $this->buyer->avatar,
            ] : null),
            'transaction' => $this->whenLoaded('paymentTransaction', fn () => $this->paymentTransaction ? [
                'amount' => $this->paymentTransaction->amount,
                'currency_code' => $this->paymentTransaction->currency_code,
            ] : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function shouldClearAffiliateCookie(): bool
    {
        $affiliateData = $this->affiliate_data;

        if (! is_array($affiliateData)) {
            return false;
        }

        $productId = (int) ($affiliateData['productId'] ?? $affiliateData['product_id'] ?? 0);

        if ($productId <= 0) {
            return false;
        }

        if ($this->relationLoaded('items')) {
            return $this->items->contains(fn ($item) => (int) $item->product_id === $productId);
        }

        return true;
    }
}
