<?php

namespace App\Modules\Selloff\Order\Services;

use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use Illuminate\Validation\ValidationException;

class AdminOrderService
{
    /** @var list<string> */
    private const ITEM_STATUSES = [
        'pending',
        'pending_payment',
        'payment_received',
        'processing',
        'order_processing',
        'shipped',
        'completed',
        'cancelled',
        'refunded',
        'refund_approved',
    ];

    public function markPaid(Order $order, OrderFulfillmentService $fulfillment): Order
    {
        if ($order->payment_status === 'payment_received') {
            throw ValidationException::withMessages([
                'payment_status' => ['Order payment is already marked received.'],
            ]);
        }

        $order->update([
            'payment_status' => 'payment_received',
            'status' => 'processing',
        ]);

        $order->items()->update(['order_status' => 'processing']);

        if (in_array($order->payment_method, ['bank_transfer', 'bank_transfer_email'], true)
            || $order->payment_status === 'awaiting_payment') {
            return $fulfillment->finalizePaidOrder($order);
        }

        return $order->fresh()->load(['items.product', 'buyer']);
    }

    public function cancel(Order $order): Order
    {
        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['Order is already cancelled.'],
            ]);
        }

        $order->update([
            'status' => 'cancelled',
        ]);

        $order->items()->update(['order_status' => 'cancelled']);

        return $order->fresh()->load(['items.product', 'buyer']);
    }

    public function updateItemStatus(Order $order, OrderItem $item, string $status): OrderItem
    {
        abort_unless((int) $item->order_id === (int) $order->id, 404);

        if (! in_array($status, self::ITEM_STATUSES, true)) {
            throw ValidationException::withMessages([
                'order_status' => ['Invalid item status.'],
            ]);
        }

        $item->update(['order_status' => $status]);

        return $item->fresh();
    }

    public function approveGuestItem(Order $order, OrderItem $item): OrderItem
    {
        abort_unless((int) $item->order_id === (int) $order->id, 404);
        abort_unless($order->buyer_id === null, 422, 'Order is not a guest order.');

        $item->update(['is_approved' => true]);

        return $item->fresh();
    }

    public function deleteItem(Order $order, OrderItem $item): void
    {
        abort_unless((int) $item->order_id === (int) $order->id, 404);

        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => ['Cancelled orders cannot be modified.'],
            ]);
        }

        $item->delete();
    }
}
