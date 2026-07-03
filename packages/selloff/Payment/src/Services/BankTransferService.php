<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Cart\Services\CommerceGtmService;
use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Services\OrderFulfillmentService;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use App\Support\Gtm\OrderGtmService;
use Illuminate\Validation\ValidationException;

class BankTransferService
{
    public function __construct(
        private readonly OrderFulfillmentService $fulfillment,
        private readonly CommerceGtmService $gtm,
        private readonly OrderGtmService $orderGtm,
    ) {}

    public function submitTransfer(User $user, CheckoutSession $checkout, ?string $paymentNote = null): array
    {
        $this->assertCheckoutOwner($user, $checkout);

        if ($checkout->payment_method !== 'bank_transfer') {
            throw ValidationException::withMessages([
                'payment_method' => ['Checkout is not configured for bank transfer.'],
            ]);
        }

        $order = $this->fulfillment->fulfillProductCheckout(
            checkout: $checkout,
            buyer: $user,
            paymentMethod: 'bank_transfer',
            paymentStatus: 'awaiting_payment',
            orderStatus: 'awaiting_payment',
            paymentMetadata: ['awaiting_bank_transfer' => true],
        );

        $request = BankTransferRequest::query()->create([
            'order_number' => $order->order_number,
            'user_id' => $user->id,
            'payment_note' => $paymentNote,
            'status' => 'pending',
            'ip_address' => request()->ip(),
        ]);

        return ['order' => $order, 'bank_transfer_request' => $request];
    }

    public function approve(BankTransferRequest $request): Order
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Bank transfer request is not pending.'],
            ]);
        }

        $order = Order::query()->where('order_number', $request->order_number)->firstOrFail();
        $checkout = CheckoutSession::query()
            ->where('checkout_token', $order->checkout_token)
            ->first();

        $order->update([
            'status' => 'payment_received',
            'payment_status' => 'payment_received',
        ]);

        $order->items()->update(['order_status' => 'payment_received']);

        $request->update(['status' => 'approved']);

        $order = $this->fulfillment->finalizePaidOrder($order);

        if ($checkout) {
            $buyer = $order->buyer;
            $gtmEvents = $this->gtm->purchase($order, $checkout, $buyer);
            $this->orderGtm->storePendingEvents($order, $gtmEvents);
        }

        return $order;
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
