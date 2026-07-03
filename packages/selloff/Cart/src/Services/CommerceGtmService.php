<?php

namespace App\Modules\Selloff\Cart\Services;

use App\Models\User;
use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Order\Models\Order;
use App\Support\Gtm\GtmEventFactory;
use Illuminate\Http\Request;

class CommerceGtmService
{
    public function __construct(
        private readonly GtmEventFactory $factory,
    ) {}

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function addToCart(Product $product, int $quantity, ?User $viewer = null): array
    {
        $translation = $product->translations->firstWhere('locale', 'en')
            ?? $product->translations->first();
        $price = (float) ($product->price_discounted ?? $product->price);

        return $this->factory->list('add_to_cart', [
            'item_id' => (string) $product->id,
            'item_title' => (string) ($translation?->title ?? $product->slug ?? ''),
            'item_price' => $price,
            'quantity' => $quantity,
            'buyer_id' => $viewer ? (string) $viewer->id : '',
        ]);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function beginCheckout(Cart $cart, ?User $buyer, Request $request): array
    {
        $cart->loadMissing('items');
        $totals = app(CartService::class)->calculateTotals($cart);

        $items = $cart->items->map(fn ($item) => [
            'item_id' => (string) $item->product_id,
            'item_title' => (string) $item->product_title,
            'quantity' => (int) $item->quantity,
            'item_price' => (float) $item->unit_price,
        ])->values()->all();

        return $this->factory->list('begin_checkout', [
            'cart_id' => (string) $cart->id,
            'no_of_items' => $totals['item_count'],
            'cart_value' => $totals['subtotal'],
            'shipping_cost' => $totals['shipping_cost'],
            'items' => $items,
            'buyer_id' => $buyer ? (string) $buyer->id : '',
            'buyer_name' => $buyer ? trim($buyer->first_name.' '.$buyer->last_name) : '',
            'buyer_username' => $buyer ? (string) ($buyer->username ?? $buyer->slug ?? '') : '',
            'buyer_phone' => $buyer ? (string) ($buyer->phone_number ?? '') : '',
            'buyer_email' => $buyer ? (string) ($buyer->email ?? '') : '',
            'buyer_ip' => (string) $request->ip(),
        ]);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function beginCheckoutPending(Order $order, CheckoutSession $checkout, ?User $buyer): array
    {
        $purchaseData = $this->purchaseEventData($order, $checkout, $buyer);
        $purchaseData['payment_status'] = 'pending';

        return $this->factory->list('begin_checkout_pending', $purchaseData);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function purchase(Order $order, CheckoutSession $checkout, ?User $buyer): array
    {
        return $this->factory->list('purchase', $this->purchaseEventData($order, $checkout, $buyer));
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseEventData(Order $order, CheckoutSession $checkout, ?User $buyer): array
    {
        return [
            'order_id' => (string) $order->id,
            'checkout_id' => (string) $checkout->id,
            'cart_id' => (string) ($checkout->cart_id ?? ''),
            'buyer_id' => $buyer ? (string) $buyer->id : (string) ($order->buyer_id ?? ''),
            'buyer_name' => $buyer ? trim($buyer->first_name.' '.$buyer->last_name) : (string) ($order->buyer_name ?? ''),
            'buyer_username' => $buyer ? (string) ($buyer->username ?? $buyer->slug ?? '') : '',
            'buyer_phone' => $buyer ? (string) ($buyer->phone_number ?? '') : '',
            'buyer_email' => $buyer ? (string) ($buyer->email ?? '') : (string) ($order->guest_email ?? ''),
            'value' => (float) $order->price_subtotal,
            'transaction_fee' => 0,
            'shipping' => (float) ($order->price_shipping ?? 0),
            'payment_method' => (string) ($order->payment_method ?? ''),
            'Checkout_status' => (string) $order->status,
            'payment_status' => (string) ($order->payment_status ?? ''),
            'total_payment' => (float) $order->price_total,
            'date' => $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function buyWithEscrow(Product $product, User $buyer): array
    {
        $translation = $product->translations->firstWhere('locale', 'en')
            ?? $product->translations->first();
        $vendor = $product->vendor;

        return $this->factory->list('buy_with_escrow', [
            'item_id' => (string) $product->id,
            'item_title' => (string) ($translation?->title ?? ''),
            'item_price' => (float) ($product->price_discounted ?? $product->price),
            'buyer_id' => (string) $buyer->id,
            'buyer_email' => (string) $buyer->email,
            'seller_id' => $vendor ? (string) $vendor->id : '',
        ]);
    }
}
