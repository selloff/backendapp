<?php

namespace App\Modules\Selloff\Order\Services;

use App\Models\User;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Payout\Services\VendorEarningService;

class AdminRefundPresenter
{
    public function __construct(private readonly VendorEarningService $vendorEarnings) {}
    public function lineItem(RefundRequest $refund): ?OrderItem
    {
        if ($refund->relationLoaded('orderItem') && $refund->orderItem) {
            return $refund->orderItem;
        }

        if ($refund->order_item_id) {
            return $refund->order?->items?->firstWhere('id', $refund->order_item_id);
        }

        return $refund->order?->items?->first();
    }

    public function earning(RefundRequest $refund, ?OrderItem $lineItem): ?\App\Modules\Selloff\Payout\Models\VendorEarning
    {
        if ($lineItem === null) {
            return null;
        }

        return $this->vendorEarnings->findForOrderItem($lineItem, $refund->order);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formatListItem(RefundRequest $refund): ?array
    {
        $lineItem = $this->lineItem($refund);
        if ($lineItem === null) {
            return null;
        }

        $order = $refund->order;
        $earning = $this->earning($refund, $lineItem);
        $paymentMethod = $order?->payment_method;
        $showEarnedAmount = $earning !== null
            && $paymentMethod !== 'cash_on_delivery'
            && $paymentMethod !== 'cod';

        $buyer = $refund->buyer;
        $seller = $refund->seller;
        if ($seller === null && $lineItem->seller_id) {
            $seller = $lineItem->relationLoaded('seller') ? $lineItem->seller : null;
        }

        return [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'order_item_id' => $lineItem->id,
            'order_number' => $refund->order_number ?? $order?->order_number,
            'status' => $refund->status,
            'is_completed' => (bool) $refund->is_completed,
            'description' => $refund->description,
            'created_at' => $refund->created_at,
            'updated_at' => $refund->updated_at,
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'price_total' => $order->price_total,
                'currency_code' => $order->currency_code,
                'payment_method' => $order->payment_method,
            ] : null,
            'product_title' => $lineItem->product_title,
            'commission_rate' => $lineItem->commission_rate,
            'line_total' => $lineItem->total_price,
            'earned_amount' => $showEarnedAmount ? $earning?->earned_amount : null,
            'earned_amount_label' => $showEarnedAmount ? null : 'not_added_to_vendor_balance',
            'earned_currency_code' => $showEarnedAmount ? ($earning?->currency_code ?? $order?->currency_code) : null,
            'buyer' => $buyer ? [
                'id' => $buyer->id,
                'name' => $buyer->name,
                'email' => $buyer->email,
                'slug' => $buyer->slug,
                'username' => $buyer->username ?? $buyer->slug,
            ] : null,
            'seller' => $seller ? [
                'id' => $seller->id,
                'name' => $seller->name,
                'email' => $seller->email,
                'slug' => $seller->slug,
                'username' => $seller->username ?? $seller->slug,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatDetail(RefundRequest $refund): array
    {
        $listItem = $this->formatListItem($refund);
        if ($listItem === null) {
            abort(404, 'Refund line item was not found.');
        }

        $listItem['messages'] = $refund->messages
            ->sortBy('created_at')
            ->values()
            ->map(fn ($message) => [
                'id' => $message->id,
                'message' => $message->message,
                'is_admin' => (bool) $message->is_admin,
                'created_at' => $message->created_at,
                'user' => $message->relationLoaded('user') && $message->user ? [
                    'id' => $message->user->id,
                    'name' => $message->user->name,
                    'email' => $message->user->email,
                    'slug' => $message->user->slug,
                    'avatar_url' => $message->user->avatar_url ?? null,
                ] : null,
            ])
            ->all();

        return $listItem;
    }
}
