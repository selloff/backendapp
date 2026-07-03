<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Affiliate\Services\AffiliateAttributionService;
use App\Modules\Selloff\Cart\Services\CartService;
use App\Modules\Selloff\Cart\Services\CommerceGtmService;
use App\Modules\Selloff\Order\Http\Requests\Api\V1\GuestCheckoutRequest;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\OrderResource;
use App\Modules\Selloff\Order\Services\CheckoutService;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class GuestCheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CheckoutService $checkoutService,
        private readonly CommerceGtmService $gtm,
        private readonly AffiliateAttributionService $affiliateAttribution,
    ) {}

    public function store(GuestCheckoutRequest $request): JsonResponse
    {
        $guestToken = (string) $request->header('X-Guest-Cart-Token');
        $cart = $this->cartService->findGuestCart($guestToken);

        abort_unless($cart, 404, 'Guest cart not found.');

        if ($request->filled('shipping_data')) {
            $shipping = $request->input('shipping_data');
            $legacyMapped = [
                'sFirstName' => $shipping['sFirstName'] ?? $shipping['first_name'] ?? null,
                'sLastName' => $shipping['sLastName'] ?? $shipping['last_name'] ?? null,
                'sEmail' => $shipping['sEmail'] ?? $shipping['email'] ?? $request->string('guest_email')->toString(),
                'sPhoneNumber' => $shipping['sPhoneNumber'] ?? $shipping['phone'] ?? null,
                'name' => $shipping['name'] ?? null,
                'address' => $shipping['address'] ?? null,
                'city' => $shipping['city'] ?? null,
                'country' => $shipping['country'] ?? null,
            ];
            $cart->update([
                'shipping_data' => array_merge($cart->shipping_data ?? [], array_filter($legacyMapped)),
            ]);
        }

        $paymentMethod = $request->string('payment_method')->toString();
        $affiliateLinkId = $this->affiliateAttribution->resolveLinkIdFromRequest($request);
        $checkout = $this->checkoutService->createFromGuestCart(
            $cart->fresh(),
            $request->string('guest_email')->toString(),
            $paymentMethod,
            $affiliateLinkId,
        );

        if ($paymentMethod !== 'bank_transfer') {
            return ApiResponse::success([
                'checkout_token' => $checkout->checkout_token,
                'payment_method' => $paymentMethod,
            ], 201);
        }

        $order = $this->checkoutService->completeGuestBankTransfer(
            $checkout,
            $request->input('payment_note'),
        );

        $bankTransfer = BankTransferRequest::query()->create([
            'order_number' => $order->order_number,
            'user_id' => null,
            'payment_note' => $request->input('payment_note'),
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        return ApiResponse::success([
            'order' => [
                ...(new OrderResource($order))->resolve($request),
                'gtm_events' => $this->gtm->beginCheckoutPending($order, $checkout, null),
            ],
            'bank_transfer_request' => [
                'id' => $bankTransfer->id,
                'status' => $bankTransfer->status,
                'order_number' => $bankTransfer->order_number,
            ],
        ], 201, 'Guest order placed successfully.');
    }
}
