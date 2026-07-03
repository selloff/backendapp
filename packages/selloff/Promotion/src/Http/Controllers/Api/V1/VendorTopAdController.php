<?php

namespace App\Modules\Selloff\Promotion\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Modules\Selloff\Promotion\Services\TopAdPromotionService;
use App\Support\Gtm\ServicePaymentGtmService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorTopAdController extends Controller
{
    public function __construct(
        private readonly TopAdPromotionService $topAds,
    ) {}

    public function pricing(): JsonResponse
    {
        return ApiResponse::success($this->topAds->pricing());
    }

    public function purchase(Request $request, Product $product, ServicePaymentGtmService $gtm): JsonResponse
    {
        $data = $request->validate([
            'duration_days' => ['required', 'integer', 'in:7,14,30,60'],
            'payment_method' => ['nullable', 'string', 'in:wallet_balance,bank_transfer,paystack'],
        ]);

        $result = $this->topAds->checkout(
            $request->user(),
            $product,
            (int) $data['duration_days'],
            $data['payment_method'] ?? 'wallet_balance',
        );

        return ApiResponse::success(
            $gtm->attachPromotionCheckoutGtm($result, $request->user(), $product, $request),
            201,
        );
    }

    public function completePaystack(Request $request, PromotionTransaction $promotionTransaction, ServicePaymentGtmService $gtm): JsonResponse
    {
        abort_unless((int) $promotionTransaction->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
        ]);

        $transaction = $this->topAds->completePaystackPayment(
            $request->user(),
            $promotionTransaction,
            $data['payment_reference'],
        );

        $product = Product::query()->findOrFail($transaction->product_id);

        $payload = [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'status' => $transaction->status,
            'payment_method' => $transaction->payment_method,
            'product_id' => $transaction->product_id,
            'duration_days' => $transaction->metadata['duration_days'] ?? $transaction->day_count,
        ];

        return ApiResponse::success($gtm->attachPromotionCheckoutGtm($payload, $request->user(), $product, $request));
    }

    public function resumePayment(
        Request $request,
        PromotionTransaction $promotionTransaction,
        ServicePaymentGtmService $gtm,
    ): JsonResponse {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:wallet_balance,bank_transfer,paystack'],
        ]);

        $result = $this->topAds->resumePendingPayment(
            $request->user(),
            $promotionTransaction,
            $data['payment_method'] ?? null,
        );

        $product = Product::query()->findOrFail($promotionTransaction->product_id);

        return ApiResponse::success($gtm->attachPromotionCheckoutGtm($result, $request->user(), $product, $request));
    }
}
