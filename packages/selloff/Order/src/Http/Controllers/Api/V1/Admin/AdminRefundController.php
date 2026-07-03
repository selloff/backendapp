<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Order\Services\AdminRefundPresenter;
use App\Modules\Selloff\Order\Services\RefundService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRefundController extends Controller
{
    public function __construct(
        private readonly AdminRefundPresenter $presenter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = RefundRequest::query()
            ->with([
                'order.items',
                'orderItem',
                'buyer:id,first_name,last_name,email,slug,username',
                'seller:id,first_name,last_name,email,slug,username',
            ])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $perPage = (int) $request->input('per_page', $request->input('show', 15));
        if (! in_array($perPage, [15, 30, 60, 100], true)) {
            $perPage = 15;
        }

        $paginator = $query->paginate($perPage);
        $items = $paginator->getCollection()
            ->map(fn (RefundRequest $refund) => $this->presenter->formatListItem($refund))
            ->filter()
            ->values();

        return ApiResponse::success([
            'data' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    public function show(RefundRequest $refundRequest): JsonResponse
    {
        $refundRequest->load([
            'order.items',
            'orderItem',
            'buyer',
            'seller',
            'messages.user',
        ]);

        return ApiResponse::success($this->presenter->formatDetail($refundRequest));
    }

    public function approve(Request $request, RefundRequest $refundRequest, RefundService $refunds): JsonResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:2000']]);
        $refund = $refunds->completeAdminRefund($refundRequest, $request->user(), $data['message'] ?? null);

        return ApiResponse::success($this->presenter->formatListItem($refund->load([
            'order.items',
            'orderItem',
            'buyer',
            'seller',
        ])));
    }

    public function reject(Request $request, RefundRequest $refundRequest, RefundService $refunds): JsonResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:2000']]);
        $refund = $refunds->reject($refundRequest, $request->user(), $data['message'] ?? null);

        return ApiResponse::success([
            'id' => $refund->id,
            'status' => $refund->status,
        ]);
    }
}
