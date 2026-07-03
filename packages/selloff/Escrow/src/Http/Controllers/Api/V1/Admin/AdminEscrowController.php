<?php

namespace App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Services\EscrowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEscrowController extends Controller
{
    public function __construct(
        private readonly EscrowService $escrowService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transactions = EscrowTransaction::query()
            ->with(['buyer:id,first_name,last_name,email,slug,avatar', 'seller:id,first_name,last_name,email,slug,avatar', 'product.translations', 'product.category', 'product.images'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where('ref', 'ilike', "%{$term}%");
            })
            ->latest('id')
            ->paginate(min(100, max(15, $request->integer('per_page', 30))));

        return ApiResponse::success([
            'data' => $transactions->getCollection()->map(fn (EscrowTransaction $tx) => $this->escrowService->formatTransaction($tx)),
            'total' => $transactions->total(),
            'current_page' => $transactions->currentPage(),
            'last_page' => $transactions->lastPage(),
        ]);
    }

    public function show(EscrowTransaction $escrowTransaction): JsonResponse
    {
        $escrowTransaction->load(['buyer', 'seller', 'product.translations', 'product.category', 'product.images', 'order']);

        return ApiResponse::success($this->escrowService->formatTransaction($escrowTransaction));
    }

    public function updateStatus(Request $request, EscrowTransaction $escrowTransaction): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending_agreement,awaiting_funding,funded,shipped,awaiting_acceptance,releasing,completed,cancelled,refunded,disputed,held,pending,buyer_agreed,seller_agreed,processing'],
        ]);

        $escrowTransaction->update(['status' => $data['status']]);

        return ApiResponse::success($this->escrowService->formatTransaction($escrowTransaction->fresh(['buyer', 'seller', 'product.translations', 'product.category'])));
    }

    public function updateStages(Request $request, EscrowTransaction $escrowTransaction): JsonResponse
    {
        $data = $request->validate([
            'delivery_cost' => ['sometimes', 'numeric', 'min:0'],
            'delivery_address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'payment_link_sent' => ['sometimes', 'boolean'],
            'payment_link_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'payment_received' => ['sometimes', 'boolean'],
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'offline_payment_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'seller_shipped_item' => ['sometimes', 'boolean'],
            'buyer_confirmed_item_delivery' => ['sometimes', 'boolean'],
            'seller_received_payment' => ['sometimes', 'boolean'],
            'transaction_complete' => ['sometimes', 'boolean'],
        ]);

        $transaction = $this->escrowService->updateAdminStages($escrowTransaction, $data, $request->user());

        return ApiResponse::success($this->escrowService->formatTransaction($transaction));
    }

    public function releaseNow(Request $request, EscrowTransaction $escrowTransaction): JsonResponse
    {
        $transaction = $this->escrowService->updateAdminStages($escrowTransaction, [
            'seller_received_payment' => true,
            'transaction_complete' => true,
        ], $request->user());

        return ApiResponse::success($this->escrowService->formatTransaction($transaction), 200, 'Escrow released to seller.');
    }

    public function resolveDispute(Request $request, EscrowTransaction $escrowTransaction): JsonResponse
    {
        $data = $request->validate([
            'resolution' => ['required', 'string', 'in:refund_buyer,release_seller'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $transaction = $this->escrowService->resolveDispute(
            $escrowTransaction,
            $request->user(),
            $data['resolution'],
            $data['note'] ?? null,
        );

        return ApiResponse::success($this->escrowService->formatTransaction($transaction), 200, 'Dispute resolved.');
    }

    public function events(EscrowTransaction $escrowTransaction): JsonResponse
    {
        return ApiResponse::success([
            'events' => $this->escrowService->formatTransaction($escrowTransaction)['events'] ?? [],
        ]);
    }
}
