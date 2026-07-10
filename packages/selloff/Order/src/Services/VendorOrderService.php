<?php

namespace App\Modules\Selloff\Order\Services;

use App\Models\User;
use App\Modules\Selloff\Notification\Services\OrderNotificationService;
use App\Modules\Selloff\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorOrderService
{
    /** @var list<string> */
    private const ALLOWED_STATUSES = ['processing', 'shipped', 'completed'];

    public function __construct(
        private readonly OrderNotificationService $notifications,
    ) {}

    public function updateStatus(Order $order, User $vendor, string $status, ?string $trackingNumber = null): Order
    {
        abort_unless(
            $order->items()->where('seller_id', $vendor->id)->exists(),
            404,
        );

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Status must be one of: '.implode(', ', self::ALLOWED_STATUSES).'.'],
            ]);
        }

        return DB::transaction(function () use ($order, $vendor, $status, $trackingNumber) {
            $wasShipped = $order->status === 'shipped';
            $snapshot = $order->shipping_snapshot ?? [];

            if ($trackingNumber !== null && $trackingNumber !== '') {
                $snapshot['tracking_number'] = $trackingNumber;
            }

            $order->update([
                'status' => $status,
                'shipping_snapshot' => $snapshot,
            ]);

            $itemUpdates = ['order_status' => $status];

            if ($status === 'shipped' && $trackingNumber !== null && $trackingNumber !== '') {
                $itemUpdates['shipping_tracking_number'] = $trackingNumber;
            }

            $order->items()->where('seller_id', $vendor->id)->update($itemUpdates);

            if ($status === 'completed') {
                app(\App\Modules\Selloff\Escrow\Services\EscrowLedgerService::class)->releaseForCompletedOrder($order);
            }

            $fresh = $order->fresh()->load(['items', 'buyer']);

            if ($status === 'shipped' && ! $wasShipped) {
                $this->notifications->queueOrderShipped(
                    $fresh,
                    (int) $vendor->id,
                    $trackingNumber,
                );
            }

            return $fresh;
        });
    }
}
