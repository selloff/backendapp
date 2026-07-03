<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Services\StripeCheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeCheckoutService $stripeCheckout): JsonResponse
    {
        $order = $stripeCheckout->completeFromWebhook(
            $request->getContent(),
            $request->header('Stripe-Signature'),
        );

        if (! $order) {
            return ApiResponse::error('Webhook ignored.', 400);
        }

        return ApiResponse::success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
        ]);
    }
}
