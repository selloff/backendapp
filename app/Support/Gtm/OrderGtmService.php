<?php

namespace App\Support\Gtm;

use App\Models\User;
use App\Modules\Selloff\Cart\Services\CommerceGtmService;
use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Models\PaymentTransaction;

class OrderGtmService
{
    public function __construct(
        private readonly CommerceGtmService $commerceGtm,
        private readonly GtmEventFactory $factory,
    ) {}

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function buildPurchaseEvents(Order $order, ?CheckoutSession $checkout, ?User $buyer): array
    {
        if ($checkout === null) {
            $checkout = CheckoutSession::query()
                ->where('checkout_token', $order->checkout_token)
                ->first();
        }

        if ($checkout === null) {
            $checkout = new CheckoutSession([
                'id' => 0,
                'cart_id' => null,
            ]);
        }

        return $this->commerceGtm->purchase($order, $checkout, $buyer);
    }

    /**
     * @param  list<array{event: string, eventData: array<string, mixed>, timestamp: int}>  $events
     */
    public function storePendingEvents(Order $order, array $events): void
    {
        $transaction = $this->resolveTransaction($order);
        if ($transaction === null) {
            return;
        }

        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $metadata['gtm_events'] = $events;
        $metadata['gtm_purchase_sent_at'] = null;

        $transaction->update(['metadata' => $metadata]);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function consumeStoredEvents(Order $order): array
    {
        $transaction = $this->resolveTransaction($order);
        if ($transaction === null) {
            return [];
        }

        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $events = $metadata['gtm_events'] ?? [];

        if (! is_array($events) || $events === []) {
            return [];
        }

        if (! empty($metadata['gtm_purchase_sent_at'])) {
            return [];
        }

        $metadata['gtm_purchase_sent_at'] = now()->toIso8601String();
        $transaction->update(['metadata' => $metadata]);

        return $events;
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function peekStoredEvents(Order $order): array
    {
        $transaction = $this->resolveTransaction($order);
        if ($transaction === null) {
            return [];
        }

        $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
        $events = $metadata['gtm_events'] ?? [];

        return is_array($events) ? $events : [];
    }

    private function resolveTransaction(Order $order): ?PaymentTransaction
    {
        return PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();
    }
}
