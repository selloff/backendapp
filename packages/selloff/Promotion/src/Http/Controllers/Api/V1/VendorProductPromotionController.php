<?php

namespace App\Modules\Selloff\Promotion\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Modules\Selloff\Promotion\Services\FeaturedPromotionService;
use App\Support\ApiResponse;
use App\Support\Gtm\ServicePaymentGtmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorProductPromotionController extends Controller
{
    public function __construct(
        private readonly FeaturedPromotionService $featuredPromotion,
    ) {}

    public function pricing(): JsonResponse
    {
        return ApiResponse::success($this->featuredPromotion->pricing());
    }

    public function store(Request $request, Product $product, ServicePaymentGtmService $gtm): JsonResponse
    {
        $data = $request->validate([
            'plan_type' => ['required', 'string', 'in:daily,monthly'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:365'],
            'payment_method' => ['nullable', 'string', 'in:wallet_balance,bank_transfer,paystack,stripe'],
        ]);

        $result = $this->featuredPromotion->checkout(
            $request->user(),
            $product,
            $data['plan_type'],
            $data['duration'] ?? 1,
            $data['payment_method'] ?? 'wallet_balance',
        );

        return ApiResponse::success(
            $gtm->attachPromotionCheckoutGtm($result, $request->user(), $product, $request),
            201,
        );
    }

    public function completePaystack(
        Request $request,
        PromotionTransaction $promotionTransaction,
        ServicePaymentGtmService $gtm,
    ): JsonResponse {
        abort_unless((int) $promotionTransaction->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
        ]);

        $transaction = $this->featuredPromotion->completePaystackPayment(
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
        ];

        return ApiResponse::success($gtm->attachPromotionCheckoutGtm($payload, $request->user(), $product, $request));
    }
}
