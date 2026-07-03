<?php

namespace App\Modules\Selloff\Escrow\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Services\EscrowFundingService;
use App\Modules\Selloff\Escrow\Services\EscrowService;
use App\Modules\Selloff\Payment\Gateways\PaystackGateway;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EscrowController extends Controller
{
    public function __construct(
        private readonly EscrowService $service,
        private readonly EscrowFundingService $funding,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $transactions = $this->service->listForBuyer($request->user(), $data['status'] ?? null);

        return ApiResponse::success(
            $transactions->map(fn (EscrowTransaction $tx) => $this->service->formatTransaction($tx))->values()->all(),
        );
    }

    public function showByToken(string $token): JsonResponse
    {
        $transaction = $this->service->findByAgreementToken($token);

        if (! $transaction) {
            return ApiResponse::error('Escrow transaction was not found.', 404);
        }

        return ApiResponse::success($this->service->formatTransaction($transaction, $token));
    }

    public function confirm(string $token): JsonResponse
    {
        $transaction = $this->service->confirmByToken($token);

        return ApiResponse::success($this->service->formatTransaction($transaction, $token), 200, 'Escrow agreement confirmed.');
    }

    public function dispute(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $transaction = $this->service->disputeByToken($token, $data['reason'] ?? null);

        return ApiResponse::success($this->service->formatTransaction($transaction, $token), 200, 'Escrow dispute submitted.');
    }

    public function confirmShipped(string $token): JsonResponse
    {
        $transaction = $this->service->confirmShippedByToken($token);

        return ApiResponse::success($this->service->formatTransaction($transaction, $token), 200, 'Shipment confirmed.');
    }

    public function confirmDelivery(string $token): JsonResponse
    {
        $transaction = $this->service->confirmDeliveryByToken($token);

        return ApiResponse::success($this->service->formatTransaction($transaction, $token), 200, 'Delivery confirmed.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = EscrowTransaction::query()
            ->with(['buyer', 'seller', 'product.translations', 'product.images'])
            ->findOrFail($id);

        abort_unless(
            $request->user()->id === $transaction->buyer_id || $request->user()->id === $transaction->seller_id,
            403,
        );

        return ApiResponse::success($this->service->formatTransaction($transaction));
    }

    public function initiate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $transaction = $this->service->initiate($request->user(), $data['product_id']);
        $transaction->load(['product.translations', 'product.vendor', 'product.images']);

        return ApiResponse::success(array_merge(
            $this->service->formatTransaction($transaction),
            ['gtm_events' => $this->service->gtmEventsForInitiate($transaction->product, $request->user())],
        ), 201);
    }

    public function pay(Request $request, EscrowTransaction $escrowTransaction, PaystackGateway $paystack): JsonResponse
    {
        abort_unless($request->user()->id === $escrowTransaction->buyer_id, 403);

        $data = $request->validate([
            'payment_method' => ['required', 'string', 'in:wallet_balance,paystack'],
        ]);

        if ($data['payment_method'] === 'wallet_balance') {
            $transaction = $this->funding->payWithWallet($request->user(), $escrowTransaction);

            return ApiResponse::success($this->service->formatTransaction($transaction), 200, 'Escrow payment completed.');
        }

        $checkout = $this->funding->initPaystackPayment($request->user(), $escrowTransaction, $paystack);

        return ApiResponse::success([
            'escrow' => $this->service->formatTransaction($escrowTransaction->fresh(['buyer', 'seller', 'product.translations', 'product.images'])),
            'checkout' => $checkout,
        ]);
    }

    public function completePaystack(Request $request, EscrowTransaction $escrowTransaction, PaystackGateway $paystack): JsonResponse
    {
        abort_unless($request->user()->id === $escrowTransaction->buyer_id, 403);

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:120'],
        ]);

        $transaction = $this->funding->completePaystackPayment(
            $request->user(),
            $escrowTransaction,
            $data['payment_reference'],
            $paystack,
        );

        return ApiResponse::success($this->service->formatTransaction($transaction), 200, 'Escrow payment completed.');
    }
}
