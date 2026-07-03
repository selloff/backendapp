<?php

namespace App\Modules\Selloff\Order\Services;

use App\Models\User;
use App\Modules\Selloff\Order\Models\Order;
use Illuminate\Validation\ValidationException;

class BuyerCancelOrderService
{
    public function cancel(Order $order, User $buyer): Order
    {
        abort_unless((int) $order->buyer_id === (int) $buyer->id, 403);

        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'order' => ['This order is already cancelled.'],
            ]);
        }

        $hasShippedItem = $order->items()
            ->where('order_status', 'shipped')
            ->exists();

        if ($hasShippedItem) {
            throw ValidationException::withMessages([
                'order' => ['Shipped orders cannot be cancelled.'],
            ]);
        }

        if ($order->payment_status === 'payment_received') {
            throw ValidationException::withMessages([
                'order' => ['Paid orders cannot be cancelled from the buyer account.'],
            ]);
        }

        if ($order->payment_method === 'cash_on_delivery' && $order->created_at->diffInHours(now()) > 24) {
            throw ValidationException::withMessages([
                'order' => ['Cash on delivery orders can only be cancelled within 24 hours.'],
            ]);
        }

        $order->items()->update(['order_status' => 'cancelled']);
        $order->update(['status' => 'cancelled']);

        return $order->fresh(['items.product', 'buyer']);
    }

    public function canCancel(Order $order, User $buyer): bool
    {
        if ((int) $order->buyer_id !== (int) $buyer->id || $order->status === 'cancelled') {
            return false;
        }

        if ($order->items()->where('order_status', 'shipped')->exists()) {
            return false;
        }

        if ($order->payment_status === 'payment_received') {
            return false;
        }

        if ($order->payment_method === 'cash_on_delivery' && $order->created_at->diffInHours(now()) > 24) {
            return false;
        }

        return true;
    }
}
