<?php

namespace App\Modules\Selloff\Payment\Gateways;

use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Payment\Contracts\StripeGatewayInterface;

class FakeStripeGateway implements StripeGatewayInterface
{
    public function createCheckoutSession(CheckoutSession $checkout): array
    {
        return [
            'payment_url' => 'https://checkout.stripe.test/pay/'.$checkout->checkout_token,
            'session_id' => 'cs_test_'.$checkout->checkout_token,
        ];
    }

    public function parseWebhookPayload(string $payload, ?string $signatureHeader): ?array
    {
        $decoded = json_decode($payload, true) ?? [];

        if (! empty($decoded['checkout_token'])) {
            return [
                'checkout_token' => (string) $decoded['checkout_token'],
                'session_id' => (string) ($decoded['session_id'] ?? 'cs_test_fake'),
            ];
        }

        return null;
    }
}
