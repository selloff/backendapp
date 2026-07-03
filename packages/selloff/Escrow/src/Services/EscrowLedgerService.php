<?php

namespace App\Modules\Selloff\Escrow\Services;

use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Support\EscrowStatus;
use App\Modules\Selloff\Order\Models\Order;

class EscrowLedgerService
{
    public function __construct(
        private readonly EscrowReleaseService $release,
    ) {}

    public function recordForOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if (! $item->seller_id) {
                continue;
            }

            $commissionRate = (float) ($item->commission_rate ?? 0);
            $amount = (float) $item->total_price;
            $commissionAmount = round($amount * ($commissionRate / 100), 2);
            $sellerAmount = round($amount - $commissionAmount, 2);

            EscrowTransaction::query()->create([
                'buyer_id' => $order->buyer_id,
                'seller_id' => $item->seller_id,
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'amount' => $amount,
                'commission_amount' => $commissionAmount,
                'seller_amount' => $sellerAmount,
                'currency_code' => $order->currency_code,
                'status' => EscrowStatus::HELD,
                'payment_received' => true,
                'metadata' => [
                    'order_number' => $order->order_number,
                    'commission_rate' => $commissionRate,
                ],
            ]);
        }
    }

    public function releaseForCompletedOrder(Order $order): void
    {
        $rows = EscrowTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', EscrowStatus::HELD)
            ->get();

        foreach ($rows as $transaction) {
            $this->release->releaseHeldOrderEscrow($transaction);
        }
    }
}
