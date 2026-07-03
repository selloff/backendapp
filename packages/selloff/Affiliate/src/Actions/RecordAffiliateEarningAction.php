<?php

namespace App\Modules\Selloff\Affiliate\Actions;

use App\Modules\Selloff\Affiliate\Models\AffiliateEarning;
use App\Modules\Selloff\Affiliate\Services\AffiliateProgramSettingsService;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use App\Modules\Selloff\Payout\Models\VendorEarning;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordAffiliateEarningAction
{
    public function __construct(
        private readonly AffiliateProgramSettingsService $program,
    ) {}

    public function execute(Order $order): void
    {
        $affiliateData = $order->affiliate_data;

        if (! is_array($affiliateData) || empty($affiliateData['productId'] ?? $affiliateData['product_id'] ?? null)) {
            return;
        }

        $settings = $this->program->programSettings();

        if (! filter_var($settings['status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $commission = (float) ($affiliateData['commission'] ?? 0);

        if ($commission <= 0) {
            return;
        }

        $productId = (int) ($affiliateData['productId'] ?? $affiliateData['product_id'] ?? 0);
        $orderItem = $order->items->firstWhere('product_id', $productId);

        if (! $orderItem) {
            return;
        }

        if (AffiliateEarning::query()->where('order_id', $order->id)->where('product_id', $productId)->exists()) {
            return;
        }

        $isSellerBased = ($settings['type'] ?? '') === 'seller_based';
        $referrerId = (int) ($affiliateData['referrerId'] ?? $affiliateData['referrer_id'] ?? 0);
        $sellerId = (int) ($affiliateData['sellerId'] ?? $affiliateData['seller_id'] ?? 0);

        DB::transaction(function () use ($order, $affiliateData, $orderItem, $commission, $isSellerBased, $productId, $referrerId, $sellerId) {
            AffiliateEarning::query()->create([
                'order_id' => $order->id,
                'referrer_id' => $referrerId,
                'product_id' => $productId,
                'seller_id' => $sellerId,
                'commission_rate' => (float) ($affiliateData['commissionRate'] ?? $affiliateData['commission_rate'] ?? 0),
                'earned_amount' => $commission,
                'currency_code' => $order->currency_code,
                'exchange_rate' => $order->exchange_rate,
            ]);

            $referrer = User::query()->whereKey($referrerId)->lockForUpdate()->first();

            if ($referrer) {
                $newBalance = round((float) $referrer->wallet_balance + $commission, 2);
                $referrer->update(['wallet_balance' => $newBalance]);

                WalletTransaction::query()->create([
                    'user_id' => $referrer->id,
                    'type' => 'affiliate_commission',
                    'amount' => $commission,
                    'balance_after' => $newBalance,
                    'description' => 'Affiliate commission for order #'.$order->order_number,
                    'order_id' => $order->id,
                ]);
            }

            if ($isSellerBased) {
                $this->adjustVendorEarning($order, $orderItem, $affiliateData, $commission);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $affiliateData
     */
    private function adjustVendorEarning(Order $order, OrderItem $orderItem, array $affiliateData, float $commission): void
    {
        $discount = (float) ($affiliateData['discount'] ?? 0);
        $deduction = round($commission + $discount, 2);

        if ($deduction <= 0) {
            return;
        }

        $earning = VendorEarning::query()
            ->where('order_id', $order->id)
            ->where('seller_id', $orderItem->seller_id)
            ->first();

        if (! $earning) {
            return;
        }

        $earning->update([
            'earned_amount' => max(0, round((float) $earning->earned_amount - $deduction, 2)),
            'affiliate_data' => $affiliateData,
        ]);
    }
}
