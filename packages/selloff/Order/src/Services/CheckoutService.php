<?php

namespace App\Modules\Selloff\Order\Services;

use App\Models\User;
use App\Modules\Selloff\Cart\Models\Cart;
use App\Modules\Selloff\Cart\Services\CartService;
use App\Modules\Selloff\Order\Models\CheckoutItem;
use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Payment\Models\WalletTransaction;
use App\Modules\Selloff\Payment\Services\PaymentMethodRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly OrderFulfillmentService $fulfillment,
        private readonly PaymentMethodRegistry $paymentMethods,
    ) {}

    public function createFromCart(User $user, string $paymentMethod, ?int $affiliateLinkId = null): CheckoutSession
    {
        $enabledKeys = collect($this->paymentMethods->cartMethods())
            ->where('enabled', true)
            ->pluck('key')
            ->all();

        if (! in_array($paymentMethod, $enabledKeys, true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Unsupported or disabled payment method.'],
            ]);
        }

        $cart = $this->cartService->resolveCart($user);
        $cart->load(['items.product.category']);
        $totals = $this->cartService->calculateTotals($cart, null, $affiliateLinkId);

        if (! $totals['is_valid'] || $cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Your cart is empty or contains unavailable items.'],
            ]);
        }

        CheckoutSession::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'user_id' => $user->id,
            'checkout_token' => (string) Str::uuid(),
            'checkout_type' => 'product',
            'payment_method' => $paymentMethod,
            'subtotal' => $totals['subtotal'],
            'shipping_cost' => $totals['shipping_cost'],
            'grand_total' => $totals['total'],
            'grand_total_base' => $totals['total'],
            'currency_code' => $totals['currency_code'],
            'currency_code_base' => $totals['currency_code'],
            'exchange_rate' => $cart->exchange_rate ?? 1,
            'cart_totals_data' => $totals,
            'shipping_data' => $cart->shipping_data,
            'shipping_cost_data' => $cart->shipping_cost_data,
            'coupon_code' => $cart->coupon_code,
            'has_physical_product' => $cart->items->contains(fn ($item) => $item->product_type === 'physical'),
            'has_digital_product' => $cart->items->contains(fn ($item) => $item->product_type === 'digital'),
            'status' => 'active',
            'transaction_number' => $this->generateTransactionNumber($paymentMethod),
            'expires_at' => now()->addHour(),
        ]);

        foreach ($cart->items as $item) {
            CheckoutItem::query()->create([
                'checkout_session_id' => $checkout->id,
                'product_id' => $item->product_id,
                'seller_id' => $item->seller_id,
                'product_type' => $item->product_type,
                'listing_type' => $item->listing_type,
                'product_title' => $item->product_title,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'unit_price_base' => $item->unit_price_base,
                'total_price' => $item->total_price,
                'product_vat' => $item->product_vat,
                'product_vat_rate' => $item->product_vat_rate,
                'product_image_data' => $item->product_image_data,
                'product_options_snapshot' => $item->product_options_snapshot,
                'product_options_summary' => $item->product_options_summary,
                'extra_options' => $item->extra_options,
                'product_commission_rate' => $item->product?->category?->commission_rate,
            ]);
        }

        return $checkout->load('items.product');
    }

    public function createFromGuestCart(Cart $cart, string $guestEmail, string $paymentMethod, ?int $affiliateLinkId = null): CheckoutSession
    {
        abort_unless($cart->guest_token && ! $cart->user_id, 403, 'Cart is not a guest cart.');

        $enabledKeys = collect($this->paymentMethods->cartMethods())
            ->where('enabled', true)
            ->pluck('key')
            ->all();

        if (! in_array($paymentMethod, $enabledKeys, true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Unsupported or disabled payment method.'],
            ]);
        }

        if ($paymentMethod === 'wallet_balance') {
            throw ValidationException::withMessages([
                'payment_method' => ['Wallet payment is not available for guest checkout.'],
            ]);
        }

        $cart->load(['items.product.category']);
        $totals = $this->cartService->calculateTotals($cart, null, $affiliateLinkId);

        if (! $totals['is_valid'] || $cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Your cart is empty or contains unavailable items.'],
            ]);
        }

        CheckoutSession::query()
            ->where('cart_id', $cart->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'user_id' => null,
            'guest_email' => $guestEmail,
            'checkout_token' => (string) Str::uuid(),
            'checkout_type' => 'product',
            'payment_method' => $paymentMethod,
            'subtotal' => $totals['subtotal'],
            'shipping_cost' => $totals['shipping_cost'],
            'grand_total' => $totals['total'],
            'grand_total_base' => $totals['total'],
            'currency_code' => $totals['currency_code'],
            'currency_code_base' => $totals['currency_code'],
            'exchange_rate' => $cart->exchange_rate ?? 1,
            'cart_totals_data' => $totals,
            'shipping_data' => $cart->shipping_data,
            'shipping_cost_data' => $cart->shipping_cost_data,
            'coupon_code' => $cart->coupon_code,
            'has_physical_product' => $cart->items->contains(fn ($item) => $item->product_type === 'physical'),
            'has_digital_product' => $cart->items->contains(fn ($item) => $item->product_type === 'digital'),
            'status' => 'active',
            'transaction_number' => $this->generateTransactionNumber($paymentMethod),
            'expires_at' => now()->addHour(),
        ]);

        foreach ($cart->items as $item) {
            CheckoutItem::query()->create([
                'checkout_session_id' => $checkout->id,
                'product_id' => $item->product_id,
                'seller_id' => $item->seller_id,
                'product_type' => $item->product_type,
                'listing_type' => $item->listing_type,
                'product_title' => $item->product_title,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'unit_price_base' => $item->unit_price_base,
                'total_price' => $item->total_price,
                'product_vat' => $item->product_vat,
                'product_vat_rate' => $item->product_vat_rate,
                'product_image_data' => $item->product_image_data,
                'product_options_snapshot' => $item->product_options_snapshot,
                'product_options_summary' => $item->product_options_summary,
                'extra_options' => $item->extra_options,
                'product_commission_rate' => $item->product?->category?->commission_rate,
            ]);
        }

        return $checkout->load('items.product');
    }

    public function completeGuestBankTransfer(CheckoutSession $checkout, ?string $paymentNote = null): Order
    {
        $this->assertGuestCheckout($checkout);

        if ($checkout->payment_method !== 'bank_transfer') {
            throw ValidationException::withMessages([
                'payment_method' => ['This checkout is not configured for bank transfer.'],
            ]);
        }

        return $this->fulfillment->fulfillProductCheckout(
            checkout: $checkout,
            buyer: null,
            paymentMethod: 'bank_transfer',
            paymentStatus: 'awaiting_payment',
            orderStatus: 'awaiting_payment',
            paymentMetadata: ['awaiting_bank_transfer' => true, 'payment_note' => $paymentNote],
            guestEmail: $checkout->guest_email,
        );
    }

    public function completeWalletPayment(User $user, CheckoutSession $checkout): Order
    {
        $this->assertCheckoutOwner($user, $checkout);

        if ($checkout->payment_method !== 'wallet_balance') {
            throw ValidationException::withMessages([
                'payment_method' => ['This checkout is not configured for wallet payment.'],
            ]);
        }

        $grandTotal = (float) $checkout->grand_total;

        if ((float) $user->wallet_balance < $grandTotal) {
            throw ValidationException::withMessages([
                'wallet_balance' => ['Insufficient wallet balance.'],
            ]);
        }

        return DB::transaction(function () use ($user, $checkout, $grandTotal) {
            $newBalance = round((float) $user->wallet_balance - $grandTotal, 2);
            $user->update(['wallet_balance' => $newBalance]);

            $order = $this->fulfillment->fulfillProductCheckout(
                checkout: $checkout,
                buyer: $user,
                paymentMethod: 'wallet_balance',
                paymentStatus: 'payment_received',
                orderStatus: 'payment_received',
            );

            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'type' => 'expense',
                'amount' => $grandTotal,
                'balance_after' => $newBalance,
                'description' => 'Order #'.$order->order_number,
                'order_id' => $order->id,
            ]);

            return $order;
        });
    }

    public function assertCheckoutOwner(User $user, CheckoutSession $checkout): void
    {
        if ((int) $checkout->user_id !== (int) $user->id) {
            abort(403, 'Checkout session does not belong to this user.');
        }

        $this->assertActiveCheckout($checkout);
    }

    public function assertGuestCheckout(CheckoutSession $checkout): void
    {
        if ($checkout->user_id !== null || ! $checkout->guest_email) {
            abort(403, 'Checkout session is not a guest checkout.');
        }

        $this->assertActiveCheckout($checkout);
    }

    private function assertActiveCheckout(CheckoutSession $checkout): void
    {
        if ($checkout->status !== 'active') {
            throw ValidationException::withMessages([
                'checkout_token' => ['Checkout session is no longer active.'],
            ]);
        }

        if ($checkout->expires_at && $checkout->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'checkout_token' => ['Checkout session has expired.'],
            ]);
        }
    }

    private function generateTransactionNumber(string $paymentMethod): string
    {
        $prefix = match ($paymentMethod) {
            'wallet_balance' => 'WLT',
            'bank_transfer' => 'BNK',
            'paystack' => 'PSK',
            'stripe' => 'STR',
            default => 'TXN',
        };

        return $prefix.now()->format('ymdHis').random_int(1000, 9999);
    }
}
