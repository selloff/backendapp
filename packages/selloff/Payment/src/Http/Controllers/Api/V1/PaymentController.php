<?php

namespace App\Modules\Selloff\Payment\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\OrderResource;
use App\Modules\Selloff\Payment\Http\Requests\Api\V1\CompleteWalletDepositPaystackRequest;
use App\Modules\Selloff\Payment\Http\Requests\Api\V1\CompleteWalletDepositRequest;
use App\Modules\Selloff\Payment\Http\Requests\Api\V1\StoreWalletDepositRequest;
use App\Modules\Selloff\Payment\Models\BankTransferRequest;
use App\Modules\Selloff\Payment\Models\WalletDeposit;
use App\Modules\Selloff\Payment\Services\PaymentMethodRegistry;
use App\Modules\Selloff\Payment\Services\WalletDepositService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function methods(Request $request, PaymentMethodRegistry $registry): JsonResponse
    {
        $context = $request->string('context')->toString();
        $methods = match ($context) {
            'service' => $registry->serviceMethods(),
            'cart' => $registry->cartMethods(),
            default => $registry->cartMethods(),
        };

        return ApiResponse::success(['methods' => $methods]);
    }

    public function storeWalletDeposit(StoreWalletDepositRequest $request, WalletDepositService $service): JsonResponse
    {
        $user = $request->user();
        $deposit = $service->createDeposit(
            $user,
            (float) $request->input('amount'),
            $request->string('payment_method')->toString(),
        );

        $payload = [
            'id' => $deposit->id,
            'amount' => $deposit->amount,
            'status' => $deposit->status,
            'payment_method' => $deposit->payment_method,
            'transaction_id' => $deposit->transaction_id,
        ];

        if ($deposit->payment_method === 'paystack' && $deposit->status === 'pending') {
            $payload['requires_action'] = true;
            $payload['checkout'] = $service->paystackCheckout($deposit, $user);
        }

        return ApiResponse::success($payload, 201);
    }

    public function completeWalletDepositPaystack(
        CompleteWalletDepositPaystackRequest $request,
        WalletDeposit $walletDeposit,
        WalletDepositService $service,
    ): JsonResponse {
        $deposit = $service->completePaystackDeposit(
            $request->user(),
            $walletDeposit,
            $request->string('payment_reference')->toString(),
        );

        return ApiResponse::success([
            'id' => $deposit->id,
            'status' => $deposit->status,
            'amount' => $deposit->amount,
            'payment_method' => $deposit->payment_method,
        ]);
    }

    public function completeWalletDeposit(
        CompleteWalletDepositRequest $request,
        WalletDeposit $walletDeposit,
        WalletDepositService $service,
    ): JsonResponse {
        abort_unless($request->user()->can('payment_settings'), 403);

        $deposit = $service->completeDeposit($walletDeposit);

        return ApiResponse::success([
            'id' => $deposit->id,
            'status' => $deposit->status,
        ]);
    }

    public function approveBankTransfer(BankTransferRequest $bankTransferRequest, \App\Modules\Selloff\Payment\Services\BankTransferService $service): JsonResponse
    {
        abort_unless(request()->user()?->can('payment_settings'), 403);

        $order = $service->approve($bankTransferRequest);

        return ApiResponse::success(new OrderResource($order));
    }
}
