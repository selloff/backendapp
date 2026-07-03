<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Cart\Services\CommerceGtmService;
use App\Modules\Selloff\Affiliate\Services\AffiliateAttributionService;
use App\Modules\Selloff\Order\Http\Requests\Api\V1\CompletePaystackCheckoutRequest;
use App\Modules\Selloff\Order\Http\Requests\Api\V1\CompleteWalletCheckoutRequest;
use App\Modules\Selloff\Order\Http\Requests\Api\V1\CreateCheckoutRequest;
use App\Modules\Selloff\Order\Http\Requests\Api\V1\InitiatePaystackCheckoutRequest;
use App\Modules\Selloff\Order\Http\Requests\Api\V1\SubmitBankTransferRequest;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\CheckoutResource;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\OrderResource;
use App\Modules\Selloff\Order\Models\CheckoutSession;
use App\Modules\Selloff\Order\Services\CheckoutPaystackService;
use App\Modules\Selloff\Order\Services\CheckoutService;
use App\Modules\Selloff\Payment\Services\BankTransferService;
use App\Support\ApiResponse;
use App\Support\Gtm\OrderGtmService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService,
        private readonly CheckoutPaystackService $paystackCheckout,
        private readonly BankTransferService $bankTransfer,
        private readonly CommerceGtmService $gtm,
        private readonly OrderGtmService $orderGtm,
        private readonly AffiliateAttributionService $affiliateAttribution,
    ) {}

    public function store(CreateCheckoutRequest $request): JsonResponse
    {
        $affiliateLinkId = $this->affiliateAttribution->resolveLinkIdFromRequest($request);

        $checkout = $this->checkoutService->createFromCart(
            $request->user(),
            $request->string('payment_method')->toString(),
            $affiliateLinkId,
        );

        return ApiResponse::success(new CheckoutResource($checkout), 201);
    }

    public function completeWallet(CompleteWalletCheckoutRequest $request): JsonResponse
    {
        $checkout = CheckoutSession::query()
            ->where('checkout_token', $request->string('checkout_token')->toString())
            ->firstOrFail();

        $order = $this->checkoutService->completeWalletPayment($request->user(), $checkout);
        $gtmEvents = $this->gtm->purchase($order, $checkout, $request->user());
        $this->orderGtm->storePendingEvents($order, $gtmEvents);

        return ApiResponse::success([
            ...(new OrderResource($order))->resolve($request),
            'gtm_events' => $gtmEvents,
        ], 201, 'Order placed successfully.');
    }

    public function initiatePaystack(InitiatePaystackCheckoutRequest $request): JsonResponse
    {
        $checkout = CheckoutSession::query()
            ->where('checkout_token', $request->string('checkout_token')->toString())
            ->firstOrFail();

        $action = $this->paystackCheckout->initiate($request->user(), $checkout);

        return ApiResponse::success($action);
    }

    public function completePaystack(CompletePaystackCheckoutRequest $request): JsonResponse
    {
        $checkout = CheckoutSession::query()
            ->where('checkout_token', $request->string('checkout_token')->toString())
            ->firstOrFail();

        $result = $this->paystackCheckout->complete(
            $request->user(),
            $checkout,
            $request->string('payment_reference')->toString(),
        );

        return ApiResponse::success([
            ...(new OrderResource($result['order']))->resolve($request),
            'gtm_events' => $result['gtm_events'],
        ], 201, 'Order placed successfully.');
    }

    public function submitBankTransfer(SubmitBankTransferRequest $request): JsonResponse
    {
        $checkout = CheckoutSession::query()
            ->where('checkout_token', $request->string('checkout_token')->toString())
            ->firstOrFail();

        $result = $this->bankTransfer->submitTransfer(
            $request->user(),
            $checkout,
            $request->input('payment_note'),
        );

        $gtmEvents = $this->gtm->beginCheckoutPending($result['order'], $checkout, $request->user());

        return ApiResponse::success([
            'order' => [
                ...(new OrderResource($result['order']))->resolve($request),
                'gtm_events' => $gtmEvents,
            ],
            'bank_transfer_request' => [
                'id' => $result['bank_transfer_request']->id,
                'status' => $result['bank_transfer_request']->status,
                'order_number' => $result['bank_transfer_request']->order_number,
            ],
        ], 201);
    }
}
