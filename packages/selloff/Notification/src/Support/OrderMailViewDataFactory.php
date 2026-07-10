<?php

namespace App\Modules\Selloff\Notification\Support;

use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use Illuminate\Support\Collection;

class OrderMailViewDataFactory
{
    /** @var array<string, string> */
    private const PAYMENT_METHOD_LABELS = [
        'wallet_balance' => 'Wallet balance',
        'paystack' => 'Paystack',
        'stripe' => 'Stripe',
        'paypal' => 'PayPal',
        'bank_transfer' => 'Bank transfer',
        'bank_transfer_email' => 'Bank transfer',
        'cash_on_delivery' => 'Cash on delivery',
        'cod' => 'Cash on delivery',
    ];

    /**
     * @return array<string, mixed>
     */
    public function forBuyerOrder(Order $order): array
    {
        $order->loadMissing(['items', 'buyer']);
        $base = $this->spaBase();

        return [
            'title' => 'Thank you for your order',
            'orderNumber' => $order->order_number,
            'paymentStatus' => $this->paymentStatusLabel($order->payment_status),
            'paymentMethod' => $this->paymentMethodLabel($order->payment_method),
            'orderDate' => $order->created_at?->format('M j, Y g:i A') ?? '',
            'currencyCode' => $order->currency_code,
            'lineItems' => $this->lineItems($order->items, $order->currency_code),
            'subtotal' => $this->formatMoney($order->price_subtotal, $order->currency_code),
            'shipping' => $this->formatMoney($order->price_shipping, $order->currency_code),
            'vat' => $order->price_vat ? $this->formatMoney($order->price_vat, $order->currency_code) : null,
            'couponCode' => $order->coupon_code,
            'couponDiscount' => $order->coupon_discount > 0
                ? $this->formatMoney($order->coupon_discount, $order->currency_code)
                : null,
            'total' => $this->formatMoney($order->price_total, $order->currency_code),
            'shippingAddress' => $this->addressBlock($order->shipping_snapshot, 's'),
            'billingAddress' => $this->addressBlock($order->shipping_snapshot, 'b'),
            'orderUrl' => "{$base}/orders/{$order->order_number}",
            'buttonText' => 'See order details',
            'showOrderButton' => $order->buyer_id !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forSellerOrder(Order $order, int $sellerId): array
    {
        $order->loadMissing(['items']);
        $items = $order->items->where('seller_id', $sellerId)->values();
        $base = $this->spaBase();

        return [
            'title' => 'You have a new order',
            'orderNumber' => $order->order_number,
            'paymentStatus' => $this->paymentStatusLabel($order->payment_status),
            'paymentMethod' => $this->paymentMethodLabel($order->payment_method),
            'orderDate' => $order->created_at?->format('M j, Y g:i A') ?? '',
            'currencyCode' => $order->currency_code,
            'lineItems' => $this->lineItems($items, $order->currency_code),
            'orderUrl' => "{$base}/vendor/sales/{$order->order_number}",
            'buttonText' => 'See order details',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forShippedOrder(
        Order $order,
        OrderItem $item,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
    ): array {
        $order->loadMissing(['items']);
        $snapshot = is_array($order->shipping_snapshot) ? $order->shipping_snapshot : [];
        $trackingNumber ??= $item->shipping_tracking_number
            ?? ($snapshot['tracking_number'] ?? null);
        $trackingUrl ??= $item->shipping_tracking_url
            ?? ($snapshot['tracking_url'] ?? null);
        $base = $this->spaBase();

        return [
            'title' => 'Your order has shipped',
            'orderNumber' => $order->order_number,
            'paymentStatus' => $this->paymentStatusLabel($order->payment_status),
            'paymentMethod' => $this->paymentMethodLabel($order->payment_method),
            'orderDate' => $order->created_at?->format('M j, Y g:i A') ?? '',
            'currencyCode' => $order->currency_code,
            'trackingNumber' => $trackingNumber,
            'trackingUrl' => $trackingUrl,
            'lineItems' => $this->lineItems(collect([$item]), $order->currency_code),
            'orderUrl' => "{$base}/orders/{$order->order_number}",
            'buttonText' => 'See order details',
        ];
    }

    public function buyerEmail(Order $order): ?string
    {
        $order->loadMissing('buyer');

        if ($order->buyer?->email) {
            return trim((string) $order->buyer->email);
        }

        $snapshot = is_array($order->shipping_snapshot) ? $order->shipping_snapshot : [];
        $guest = trim((string) ($snapshot['sEmail'] ?? $snapshot['email'] ?? $order->guest_email ?? ''));

        return $guest !== '' ? $guest : null;
    }

    /**
     * @param  Collection<int, OrderItem>|array<int, OrderItem>  $items
     * @return list<array<string, mixed>>
     */
    private function lineItems(Collection|array $items, ?string $currencyCode = null): array
    {
        return collect($items)->map(function (OrderItem $item) use ($currencyCode) {
            $currency = $currencyCode ?? $item->order?->currency_code;

            return [
                'title' => $item->product_title,
                'unitPrice' => $this->formatMoney($item->unit_price, $currency),
                'quantity' => $item->quantity,
                'vat' => $item->product_vat > 0
                    ? $this->formatMoney($item->product_vat, $currency)
                    : null,
                'vatRate' => $item->product_vat_rate,
                'total' => $this->formatMoney($item->total_price, $currency),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, string|null>|null
     */
    private function addressBlock(?array $snapshot, string $prefix): ?array
    {
        if ($snapshot === null || $snapshot === []) {
            return null;
        }

        $map = [
            'firstName' => $prefix.'FirstName',
            'lastName' => $prefix.'LastName',
            'email' => $prefix === 's' ? 'sEmail' : 'bEmail',
            'phone' => $prefix.'PhoneNumber',
            'address' => $prefix.'Address',
            'country' => $prefix.'Country',
            'state' => $prefix.'State',
            'city' => $prefix.'City',
            'zipCode' => $prefix.'ZipCode',
        ];

        $block = [];
        foreach ($map as $key => $sourceKey) {
            $value = trim((string) ($snapshot[$sourceKey] ?? ''));
            $block[$key] = $value !== '' ? $value : null;
        }

        return collect($block)->filter()->isEmpty() ? null : $block;
    }

    private function paymentMethodLabel(?string $method): string
    {
        if ($method === null || $method === '') {
            return 'N/A';
        }

        return self::PAYMENT_METHOD_LABELS[$method]
            ?? ucwords(str_replace('_', ' ', $method));
    }

    private function paymentStatusLabel(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'N/A';
        }

        return ucwords(str_replace('_', ' ', $status));
    }

    private function formatMoney(mixed $amount, ?string $currency = null): string
    {
        $formatted = number_format((float) $amount, 2);

        return trim($formatted.' '.($currency ?? ''));
    }

    private function spaBase(): string
    {
        return rtrim((string) config('selloff.spa_url', config('app.url')), '/');
    }
}
