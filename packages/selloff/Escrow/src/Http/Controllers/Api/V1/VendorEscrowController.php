<?php

namespace App\Modules\Selloff\Escrow\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Escrow\Models\EscrowTransaction;
use App\Modules\Selloff\Escrow\Services\EscrowService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorEscrowController extends Controller
{
    public function __construct(
        private readonly EscrowService $escrowService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $sellerId = $request->user()->id;
        $listSt = $request->filled('st') ? $request->string('st')->toString() : null;

        $query = EscrowTransaction::query()
            ->with(['buyer:id,first_name,last_name,email,slug', 'product.translations', 'product.images'])
            ->where('seller_id', $sellerId)
            ->when($listSt === 'completed', fn (Builder $q) => $q->where(function (Builder $inner): void {
                $inner->where('status', 'completed')->orWhere('transaction_complete', true);
            }))
            ->when($listSt === 'active', fn (Builder $q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->when($listSt === 'disputed', fn (Builder $q) => $q->where('status', 'disputed'))
            ->orderByDesc('id');

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));
        $paginator->through(fn (EscrowTransaction $transaction) => $this->escrowService->formatTransaction($transaction));

        return ApiResponse::success($paginator);
    }

    public function show(Request $request, EscrowTransaction $escrowTransaction): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);
        abort_unless($escrowTransaction->seller_id === $request->user()->id, 404);

        $escrowTransaction->load(['buyer', 'seller', 'product.translations', 'product.category', 'product.images']);

        return ApiResponse::success($this->escrowService->formatTransaction(
            $escrowTransaction,
            $escrowTransaction->seller_agreement_token,
        ));
    }
}
