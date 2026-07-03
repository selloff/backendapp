<?php

namespace App\Modules\Selloff\Order\Services;

use App\Modules\Selloff\Affiliate\Actions\RecordAffiliateEarningAction;
use App\Models\User;
use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Catalog\Services\ProductPricing;
use App\Modules\Selloff\Escrow\Services\EscrowLedgerService;
use App\Modules\Selloff\Notification\Services\OrderNotificationService;
use App\Modules\Selloff\Payout\Services\VendorEarningService;
use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;
use App\Modules\Selloff\Promotion\Models\CouponUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderFulfillmentService
{
    public function __construct(
        private readonly EscrowLedgerService $escrowLedger,
        private readonly OrderNotificationService $notifications,
        private readonly VendorEarningService $vendorEarnings,
        private readonly RecordAffiliateEarningAction $recordAffiliateEarning,
    ) {}

    /**
     * @param  array<string, mixed>  $paymentMetadata
     */
    public function fulfillProductCheckout(
        CheckoutSession $checkout,
        ?User $buyer,
        string $paymentMethod,
        string $paymentStatus,
        string $orderStatus,
        ?string $transactionId = null,
        array $paymentMetadata = [],
        ?string $guestEmail = null,
    ): Order {
        return DB::transaction(function () use ($checkout, $buyer, $paymentMethod, $paymentStatus, $orderStatus, $transactionId, $paymentMetadata, $guestEmail) {
            $checkout->refresh()->load('items.product');

            if ($checkout->status !== 'active') {
                throw ValidationException::withMessages([
                    'checkout_token' => ['Checkout session is no longer active.'],
                ]);
            }

            foreach ($checkout->items as $item) {
                $product = $item->product;

                if (! $product || ! ProductPricing::isPurchasable($product, $item->quantity)) {
                    throw ValidationException::withMessages([
                        'cart' => ["Product {$item->product_title} is no longer available."],
                    ]);
                }
            }

            $affiliateData = data_get($checkout->cart_totals_data, 'affiliate_data');

            $order = Order::query()->create([
                'buyer_id' => $buyer?->id,
                'guest_email' => $guestEmail,
                'order_number' => $this->generateOrderNumber(),
                'price_subtotal' => $checkout->subtotal,
                'price_shipping' => $checkout->shipping_cost,
                'price_total' => $checkout->grand_total,
                'price_total_base' => $checkout->grand_total_base,
                'currency_code' => $checkout->currency_code,
                'currency_code_base' => $checkout->currency_code_base,
                'exchange_rate' => $checkout->exchange_rate,
                'status' => $orderStatus,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'transaction_id' => $transactionId ?? $checkout->transaction_number,
                'checkout_token' => $checkout->checkout_token,
                'shipping_snapshot' => $checkout->shipping_data,
                'affiliate_data' => is_array($affiliateData) ? $this->normalizeAffiliateData($affiliateData) : null,
            ]);

            foreach ($checkout->items as $item) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'seller_id' => $item->seller_id,
                    'product_type' => $item->product_type,
                    'product_title' => $item->product_title,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'product_vat' => $item->product_vat,
                    'product_options_snapshot' => $item->product_options_snapshot,
                    'product_options_summary' => $item->product_options_summary,
                    'commission_rate' => $item->product_commission_rate,
                    'order_status' => $orderStatus,
                ]);

                if ($paymentStatus === 'payment_received') {
                    $this->decrementStock($item->product, $item->quantity);
                }
            }

            PaymentTransaction::query()->create([
                'user_id' => $buyer?->id,
                'order_id' => $order->id,
                'transaction_number' => $checkout->transaction_number,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'amount' => $checkout->grand_total,
                'currency_code' => $checkout->currency_code,
                'exchange_rate' => $checkout->exchange_rate,
                'metadata' => array_merge(['checkout_token' => $checkout->checkout_token], $paymentMetadata),
            ]);

            if ($checkout->coupon_code) {
                CouponUsage::query()->create([
                    'order_id' => $order->id,
                    'user_id' => $buyer?->id,
                    'coupon_code' => $checkout->coupon_code,
                ]);
            }

            if ($paymentStatus === 'payment_received') {
                $this->escrowLedger->recordForOrder($order);
                $this->vendorEarnings->recordForOrder($order);
                $this->recordAffiliateEarning->execute($order->fresh(['items']));
            }

            $checkout->update(['status' => 'completed']);
            $this->clearCart($checkout);

            $order = $order->load(['items.product', 'buyer']);
            $this->notifications->queueOrderConfirmation($order);

            return $order;
        });
    }

    public function generateOrderNumber(): int
    {
        do {
            $number = (int) now()->format('ymdHis').random_int(100, 999);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    public function finalizePaidOrder(Order $order): Order
    {
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            if (! ProductPricing::isPurchasable($item->product, $item->quantity)) {
                throw ValidationException::withMessages([
                    'order' => ["Product {$item->product_title} is no longer available."],
                ]);
            }

            $this->decrementStock($item->product, $item->quantity);
        }

        $this->escrowLedger->recordForOrder($order);
        $this->vendorEarnings->recordForOrder($order);
        $this->recordAffiliateEarning->execute($order->fresh(['items']));
        $this->notifications->queueOrderConfirmation($order->fresh(['buyer']));

        return $order->fresh()->load(['items', 'buyer']);
    }

    private function decrementStock(?Product $product, int $quantity): void
    {
        if (! $product) {
            return;
        }

        $newStock = max(0, (int) $product->stock - $quantity);
        $updates = ['stock' => $newStock];

        if ($newStock === 0 && ! $product->multiple_sale) {
            $updates['is_sold'] = true;
            $updates['is_active'] = false;
        }

        $product->update($updates);
    }

    private function clearCart(CheckoutSession $checkout): void
    {
        if (! $checkout->cart_id) {
            return;
        }

        $cart = Cart::query()->find($checkout->cart_id);
        $cart?->items()->delete();
        $cart?->update(['coupon_code' => null, 'shipping_cost' => 0]);
    }

    /**
     * @param  array<string, mixed>  $affiliateData
     * @return array<string, mixed>
     */
    private function normalizeAffiliateData(array $affiliateData): array
    {
        return [
            'id' => $affiliateData['id'] ?? null,
            'referrerId' => $affiliateData['referrer_id'] ?? null,
            'sellerId' => $affiliateData['seller_id'] ?? null,
            'productId' => $affiliateData['product_id'] ?? null,
            'commissionRate' => $affiliateData['commission_rate'] ?? 0,
            'commission' => $affiliateData['commission'] ?? 0,
            'discountRate' => $affiliateData['discount_rate'] ?? 0,
            'discount' => $affiliateData['discount'] ?? 0,
        ];
    }
}
