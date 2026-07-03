<?php

namespace App\Modules\Selloff\Payout\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payout\Http\Requests\Api\V1\StorePayoutRequestRequest;
use App\Modules\Selloff\Payout\Models\PayoutRequest;
use App\Modules\Selloff\Payout\Services\PayoutService;
use App\Modules\Selloff\Payout\Services\VendorEarningService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorEarningsController extends Controller
{
    public function summary(VendorEarningService $earnings): JsonResponse
    {
        $seller = request()->user();

        return ApiResponse::success([
            'total_earned' => $earnings->totalEarned($seller),
            'available_balance' => $earnings->availableBalance($seller),
            'reserved_for_payouts' => $earnings->reservedForPayouts($seller),
            'sales_count' => $earnings->salesCount($seller),
            'active_sales_count' => $earnings->activeSalesCount($seller),
            'completed_sales_count' => $earnings->completedSalesCount($seller),
            'monthly_sales' => $earnings->monthlySalesTotals($seller),
            'products_count' => $earnings->productsCount($seller),
            'pending_products_count' => $earnings->pendingProductsCount($seller),
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $payouts = PayoutRequest::query()
            ->where('seller_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', 15), 100));

        return ApiResponse::success($payouts);
    }

    public function storePayout(StorePayoutRequestRequest $request, PayoutService $payouts): JsonResponse
    {
        $payout = $payouts->requestPayout(
            $request->user(),
            (float) $request->input('amount'),
            $request->input('payout_info'),
        );

        return ApiResponse::success([
            'id' => $payout->id,
            'amount' => $payout->amount,
            'status' => $payout->status,
        ], 201);
    }
}
