<?php

namespace App\Modules\Selloff\Payout\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use Illuminate\Validation\ValidationException;

class VendorEarningService
{
    public function recordForOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if (! $item->seller_id) {
                continue;
            }

            $commissionRate = (float) ($item->commission_rate ?? 0);
            $amount = (float) $item->total_price;
            $sellerAmount = round($amount - ($amount * ($commissionRate / 100)), 2);

            VendorEarning::query()->firstOrCreate(
                [
                    'seller_id' => $item->seller_id,
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                ],
                [
                    'earned_amount' => $sellerAmount,
                    'sale_amount' => $amount,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => round($amount * ($commissionRate / 100), 2),
                    'currency_code' => $order->currency_code,
                    'exchange_rate' => $order->exchange_rate,
                ],
            );
        }
    }

    public function findForOrderItem(OrderItem $item, ?Order $order = null): ?VendorEarning
    {
        $earning = VendorEarning::query()
            ->where('order_item_id', $item->id)
            ->first();

        if ($earning !== null) {
            return $earning;
        }

        $orderId = $order?->id ?? $item->order_id;

        return VendorEarning::query()
            ->where('order_id', $orderId)
            ->where('seller_id', $item->seller_id)
            ->first();
    }

    public function reverseEarningForRefund(VendorEarning $earning, User $seller): void
    {
        if ($earning->is_refunded) {
            return;
        }

        $seller->vendor_balance_adjustment = round(
            (float) ($seller->vendor_balance_adjustment ?? 0) - (float) $earning->earned_amount,
            2,
        );
        $seller->save();

        $earning->update(['is_refunded' => true]);
    }

    public function totalEarned(User $seller): float
    {
        return (float) VendorEarning::query()
            ->where('seller_id', $seller->id)
            ->sum('earned_amount');
    }

    public function reservedForPayouts(User $seller): float
    {
        return (float) PayoutRequest::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');
    }

    public function availableBalance(User $seller): float
    {
        $adjustment = (float) ($seller->vendor_balance_adjustment ?? 0);

        return round($this->totalEarned($seller) - $this->reservedForPayouts($seller) + $adjustment, 2);
    }

    public function setAvailableBalance(User $seller, float $targetBalance): User
    {
        $computed = round($this->totalEarned($seller) - $this->reservedForPayouts($seller), 2);
        $seller->vendor_balance_adjustment = round($targetBalance - $computed, 2);
        $seller->save();

        return $seller->refresh();
    }

    public function salesCount(User $seller): int
    {
        return $this->activeSalesCount($seller) + $this->completedSalesCount($seller);
    }

    public function activeSalesCount(User $seller): int
    {
        return Order::query()
            ->whereHas('items', fn ($query) => $query->where('seller_id', $seller->id))
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
    }

    public function completedSalesCount(User $seller): int
    {
        return Order::query()
            ->whereHas('items', fn ($query) => $query->where('seller_id', $seller->id))
            ->where('status', 'completed')
            ->count();
    }

    /** @return list<array{month: int, label: string, total: float}> */
    public function monthlySalesTotals(User $seller): array
    {
        $year = (int) now()->format('Y');
        $totals = array_fill(1, 12, 0.0);

        $items = OrderItem::query()
            ->where('seller_id', $seller->id)
            ->whereYear('created_at', $year)
            ->whereHas('order', fn ($query) => $query->where('status', 'completed'))
            ->get(['total_price', 'created_at']);

        foreach ($items as $item) {
            $month = (int) $item->created_at?->format('n');
            if ($month >= 1 && $month <= 12) {
                $totals[$month] += (float) $item->total_price;
            }
        }

        return collect($totals)->map(function (float $total, int $month) {
            return [
                'month' => $month,
                'label' => now()->setMonth($month)->format('M'),
                'total' => round($total, 2),
            ];
        })->values()->all();
    }

    public function productsCount(User $seller): int
    {
        return Product::query()
            ->where('vendor_id', $seller->id)
            ->vendorItemsForSale()
            ->count();
    }

    public function pendingProductsCount(User $seller): int
    {
        return Product::query()
            ->where('vendor_id', $seller->id)
            ->vendorPendingItems()
            ->count();
    }
}
