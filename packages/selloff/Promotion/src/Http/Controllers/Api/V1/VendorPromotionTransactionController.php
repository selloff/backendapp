<?php

namespace App\Modules\Selloff\Promotion\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Promotion\Models\PromotionTransaction;
use App\Modules\Selloff\Promotion\Services\FeaturedPromotionService;
use App\Support\ApiResponse;
use App\Support\Gtm\ServicePaymentGtmService;
use App\Support\ServiceInvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorPromotionTransactionController extends Controller
{
    public function __construct(
        private readonly FeaturedPromotionService $featuredPromotion,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = PromotionTransaction::query()
            ->with(['product:id,slug', 'product.translations'])
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        return ApiResponse::success([
            'data' => $transactions->getCollection()->map(fn (PromotionTransaction $tx) => [
                'id' => $tx->id,
                'amount' => $tx->amount,
                'currency_code' => $tx->currency_code,
                'status' => $tx->status,
                'payment_method' => $tx->payment_method,
                'purchased_plan' => $tx->purchased_plan,
                'product' => $tx->product ? [
                    'id' => $tx->product->id,
                    'slug' => $tx->product->slug,
                    'title' => $tx->product->translations->firstWhere('locale', 'en')?->title
                        ?? $tx->product->translations->first()?->title,
                ] : null,
                'created_at' => $tx->created_at,
            ]),
            'total' => $transactions->total(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
        ]);
    }

    public function invoice(Request $request, PromotionTransaction $promotionTransaction, ServiceInvoiceBuilder $invoices): JsonResponse
    {
        abort_unless((int) $promotionTransaction->user_id === (int) $request->user()->id, 403);

        return ApiResponse::success($invoices->promotion($promotionTransaction));
    }

    public function resumePayment(
        Request $request,
        PromotionTransaction $promotionTransaction,
        ServicePaymentGtmService $gtm,
    ): JsonResponse {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:wallet_balance,bank_transfer,paystack'],
        ]);

        $result = $this->featuredPromotion->resumePendingPayment(
            $request->user(),
            $promotionTransaction,
            $data['payment_method'] ?? null,
        );

        $product = Product::query()->findOrFail($promotionTransaction->product_id);

        return ApiResponse::success($gtm->attachPromotionCheckoutGtm($result, $request->user(), $product, $request));
    }
}
