<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Payment\Models\MembershipTransaction;
use App\Modules\Selloff\Payment\Services\MembershipPurchaseService;
use App\Support\ApiResponse;
use App\Support\Gtm\ServicePaymentGtmService;
use App\Support\ServiceInvoiceBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorMembershipTransactionController extends Controller
{
    public function __construct(
        private readonly MembershipPurchaseService $purchaseService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = MembershipTransaction::query()
            ->with('membershipPlan:id,title,price,currency_code')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(20);

        return ApiResponse::success([
            'data' => $transactions->getCollection()->map(fn (MembershipTransaction $tx) => [
                'id' => $tx->id,
                'payment_id' => $tx->payment_reference ?? (string) $tx->id,
                'amount' => $tx->amount,
                'currency_code' => $tx->membershipPlan?->currency_code ?? $tx->currency_code ?? 'NGN',
                'payment_method' => $tx->payment_method,
                'status' => $tx->status,
                'purchase_type' => $tx->purchase_type,
                'term_months' => $tx->term_months,
                'plan' => $tx->membershipPlan ? [
                    'id' => $tx->membershipPlan->id,
                    'title' => $tx->membershipPlan->title,
                ] : null,
                'created_at' => $tx->created_at,
            ]),
            'total' => $transactions->total(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
        ]);
    }

    public function invoice(Request $request, MembershipTransaction $membershipTransaction, ServiceInvoiceBuilder $invoices): JsonResponse
    {
        abort_unless((int) $membershipTransaction->user_id === (int) $request->user()->id, 403);

        return ApiResponse::success($invoices->membership($membershipTransaction));
    }

    public function completePaystack(Request $request, MembershipTransaction $membershipTransaction, ServicePaymentGtmService $gtm): JsonResponse
    {
        abort_unless((int) $membershipTransaction->user_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
        ]);

        $transaction = $this->purchaseService->completePaystackPayment(
            $request->user(),
            $membershipTransaction,
            $data['payment_reference'],
        );

        $payload = [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'status' => $transaction->status,
            'payment_method' => $transaction->payment_method,
            'purchase_type' => $transaction->purchase_type,
            'term_months' => $transaction->term_months,
            'plan' => $transaction->membershipPlan,
        ];

        return ApiResponse::success($gtm->attachMembershipCheckoutGtm($payload, $request->user()));
    }

    public function resumePayment(
        Request $request,
        MembershipTransaction $membershipTransaction,
        ServicePaymentGtmService $gtm,
    ): JsonResponse {
        $data = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:wallet_balance,bank_transfer,paystack'],
        ]);

        $result = $this->purchaseService->resumePendingPayment(
            $request->user(),
            $membershipTransaction,
            $data['payment_method'] ?? null,
        );

        return ApiResponse::success($gtm->attachMembershipCheckoutGtm($result, $request->user()));
    }
}
