<?php

namespace App\Modules\Selloff\Payment\Gateways;

use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Payment\Contracts\StripeGatewayInterface;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeGateway implements StripeGatewayInterface
{
    public function createCheckoutSession(CheckoutSession $checkout): array
    {
        $checkout->loadMissing('items');

        Stripe::setApiKey((string) config('selloff_payments.stripe.secret_key'));

        $lineItems = [];
        foreach ($checkout->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower((string) $checkout->currency_code ?: 'ngn'),
                    'product_data' => [
                        'name' => $item->product_title ?? 'Product',
                    ],
                    'unit_amount' => (int) round(((float) $item->unit_price) * 100),
                ],
                'quantity' => $item->quantity,
            ];
        }

        if ((float) $checkout->shipping_cost > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower((string) $checkout->currency_code ?: 'ngn'),
                    'product_data' => ['name' => 'Shipping'],
                    'unit_amount' => (int) round(((float) $checkout->shipping_cost) * 100),
                ],
                'quantity' => 1,
            ];
        }

        $session = Session::create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => config('selloff_payments.stripe.success_url').'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => (string) config('selloff_payments.stripe.cancel_url'),
            'metadata' => [
                'checkout_token' => (string) $checkout->checkout_token,
                'transaction_number' => (string) $checkout->transaction_number,
            ],
        ]);

        return [
            'payment_url' => (string) $session->url,
            'session_id' => (string) $session->id,
        ];
    }

    public function parseWebhookPayload(string $payload, ?string $signatureHeader): ?array
    {
        if (! config('selloff_payments.stripe.verify_webhook', true)) {
            $decoded = json_decode($payload, true);
            $metadata = $decoded['data']['object']['metadata'] ?? $decoded['metadata'] ?? [];

            if (! empty($metadata['checkout_token'])) {
                return [
                    'checkout_token' => (string) $metadata['checkout_token'],
                    'session_id' => (string) ($decoded['data']['object']['id'] ?? $decoded['id'] ?? 'test_session'),
                ];
            }

            return null;
        }

        $secret = (string) config('selloff_payments.stripe.webhook_secret');
        if ($secret === '' || ! $signatureHeader) {
            return null;
        }

        $event = Webhook::constructEvent($payload, $signatureHeader, $secret);

        if ($event->type !== 'checkout.session.completed') {
            return null;
        }

        /** @var Session $session */
        $session = $event->data->object;

        return [
            'checkout_token' => (string) ($session->metadata['checkout_token'] ?? ''),
            'session_id' => (string) $session->id,
        ];
    }
}
