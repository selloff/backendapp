<?php

namespace App\Modules\Selloff\Payment\Contracts;

use App\Modules\Selloff\Order\Models\CheckoutSession;

interface StripeGatewayInterface
{
    /**
     * @return array{payment_url: string, session_id: string}
     */
    public function createCheckoutSession(CheckoutSession $checkout): array;

    /**
     * @return array{checkout_token: string, session_id: string}|null
     */
    public function parseWebhookPayload(string $payload, ?string $signatureHeader): ?array;
}
