<?php

namespace App\Modules\Selloff\Order\Services;

use App\Models\User;
use App\Modules\Selloff\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorOrderService
{
    /** @var list<string> */
    private const ALLOWED_STATUSES = ['processing', 'shipped', 'completed'];

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
            $snapshot = $order->shipping_snapshot ?? [];

            if ($trackingNumber !== null && $trackingNumber !== '') {
                $snapshot['tracking_number'] = $trackingNumber;
            }

            $order->update([
                'status' => $status,
                'shipping_snapshot' => $snapshot,
            ]);

            $order->items()->where('seller_id', $vendor->id)->update(['order_status' => $status]);

            if ($status === 'completed') {
                app(\App\Modules\Selloff\Escrow\Services\EscrowLedgerService::class)->releaseForCompletedOrder($order);
            }

            return $order->fresh()->load(['items', 'buyer']);
        });
    }
}
