<?php

namespace App\Modules\Selloff\Order\Services;

use App\Models\User;
use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Gateways\PaystackGateway;
use App\Support\Gtm\OrderGtmService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutPaystackService
{
    public function __construct(
        private readonly PaystackGateway $paystack,
        private readonly CheckoutService $checkoutService,
        private readonly OrderFulfillmentService $fulfillment,
        private readonly OrderGtmService $orderGtm,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function initiate(User $user, CheckoutSession $checkout): array
    {
        $this->checkoutService->assertCheckoutOwner($user, $checkout);

        if ($checkout->payment_method !== 'paystack') {
            throw ValidationException::withMessages([
                'payment_method' => ['This checkout is not configured for Paystack payment.'],
            ]);
        }

        if (! $this->paystack->isEnabled()) {
            throw ValidationException::withMessages([
                'payment_method' => ['Paystack is not enabled.'],
            ]);
        }

        $reference = $checkout->transaction_number ?: ('ORD-'.Str::upper(Str::random(12)));
        if ($checkout->transaction_number !== $reference) {
            $checkout->update(['transaction_number' => $reference]);
        }

        $config = $this->paystack->enabledConfig();

        return [
            'type' => 'paystack_inline',
            'public_key' => $config['public_key'] ?? '',
            'email' => $user->email,
            'amount_kobo' => (int) round(((float) $checkout->grand_total) * 100),
            'reference' => $reference,
            'currency' => $checkout->currency_code ?? 'NGN',
        ];
    }

    /**
     * @return array{order: Order, gtm_events: list<array{event: string, eventData: array<string, mixed>, timestamp: int}>}
     */
    public function complete(User $user, CheckoutSession $checkout, string $paymentReference): array
    {
        $this->checkoutService->assertCheckoutOwner($user, $checkout);

        if ($checkout->payment_method !== 'paystack') {
            throw ValidationException::withMessages([
                'payment_method' => ['This checkout is not configured for Paystack payment.'],
            ]);
        }

        $verified = $this->paystack->verify($paymentReference);
        $expectedAmount = (int) round(((float) $checkout->grand_total) * 100);

        if ((int) ($verified->amount ?? 0) !== $expectedAmount) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Paystack payment amount does not match checkout total.'],
            ]);
        }

        if (strtoupper((string) ($verified->currency ?? '')) !== strtoupper((string) ($checkout->currency_code ?? 'NGN'))) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Paystack payment currency does not match checkout currency.'],
            ]);
        }

        $order = DB::transaction(function () use ($user, $checkout, $paymentReference) {
            return $this->fulfillment->fulfillProductCheckout(
                checkout: $checkout,
                buyer: $user,
                paymentMethod: 'paystack',
                paymentStatus: 'payment_received',
                orderStatus: 'payment_received',
                transactionId: $paymentReference,
                paymentMetadata: ['paystack_reference' => $paymentReference],
            );
        });

        $gtmEvents = $this->orderGtm->buildPurchaseEvents($order, $checkout, $user);
        $this->orderGtm->storePendingEvents($order, $gtmEvents);

        return [
            'order' => $order->fresh()->load(['items', 'buyer']),
            'gtm_events' => $gtmEvents,
        ];
    }
}
