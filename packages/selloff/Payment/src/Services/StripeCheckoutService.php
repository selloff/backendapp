<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Services\OrderFulfillmentService;
use App\Modules\Selloff\Payment\Contracts\StripeGatewayInterface;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use Illuminate\Validation\ValidationException;

class StripeCheckoutService
{
    public function __construct(
        private readonly StripeGatewayInterface $stripe,
        private readonly OrderFulfillmentService $fulfillment,
    ) {}

    /**
     * @return array{payment_url: string, session_id: string}
     */
    public function initiatePayment(User $user, CheckoutSession $checkout): array
    {
        $this->assertCheckoutOwner($user, $checkout);

        if ($checkout->payment_method !== 'stripe') {
            throw ValidationException::withMessages([
                'payment_method' => ['Checkout is not configured for Stripe.'],
            ]);
        }

        $result = $this->stripe->createCheckoutSession($checkout);

        $checkout->update([
            'payment_url' => $result['payment_url'],
            'transaction_number' => $checkout->transaction_number ?: ('STR'.now()->format('ymdHis')),
        ]);

        return $result;
    }

    public function completeFromWebhook(string $payload, ?string $signatureHeader): ?Order
    {
        $parsed = $this->stripe->parseWebhookPayload($payload, $signatureHeader);

        if (! $parsed || empty($parsed['checkout_token'])) {
            return null;
        }

        $checkout = CheckoutSession::query()
            ->where('checkout_token', $parsed['checkout_token'])
            ->where('status', 'active')
            ->first();

        if (! $checkout || ! $checkout->user) {
            return null;
        }

        return $this->fulfillment->fulfillProductCheckout(
            checkout: $checkout,
            buyer: $checkout->user,
            paymentMethod: 'stripe',
            paymentStatus: 'payment_received',
            orderStatus: 'payment_received',
            transactionId: $parsed['session_id'],
            paymentMetadata: ['stripe_session_id' => $parsed['session_id']],
        );
    }

    private function assertCheckoutOwner(User $user, CheckoutSession $checkout): void
    {
        if ((int) $checkout->user_id !== (int) $user->id) {
            abort(403, 'Checkout session does not belong to this user.');
        }

        if ($checkout->status !== 'active') {
            throw ValidationException::withMessages([
                'checkout_token' => ['Checkout session is no longer active.'],
            ]);
        }
    }
}
