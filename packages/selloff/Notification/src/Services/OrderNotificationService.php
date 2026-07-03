<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Order\Models\Order;

class OrderNotificationService
{
    public function queueOrderConfirmation(Order $order): EmailJob
    {
        $buyer = $order->buyer;

        return EmailJob::query()->create([
            'to_email' => $buyer?->email ?? $order->guest_email ?? 'unknown@selloff.test',
            'subject' => 'Order #'.$order->order_number.' confirmation',
            'body' => 'Your order total is '.$order->price_total.' '.$order->currency_code.'.',
            'status' => 'pending',
            'metadata' => [
                'type' => 'order_confirmation',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status,
            ],
        ]);
    }
}
