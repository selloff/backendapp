<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Order\Services\RefundService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorRefundController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $sellerId = $request->user()->id;

        $query = RefundRequest::query()
            ->with(['order', 'buyer', 'messages'])
            ->where('seller_id', $sellerId)
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id');

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));

        return ApiResponse::success($paginator);
    }

    public function approve(Request $request, RefundRequest $refundRequest, RefundService $refunds): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $data = $request->validate(['message' => ['nullable', 'string', 'max:2000']]);
        $refund = $refunds->vendorApprove($refundRequest, $request->user(), $data['message'] ?? null);

        return ApiResponse::success([
            'id' => $refund->id,
            'status' => $refund->status,
        ]);
    }

    public function reject(Request $request, RefundRequest $refundRequest, RefundService $refunds): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $data = $request->validate(['message' => ['nullable', 'string', 'max:2000']]);
        $refund = $refunds->vendorReject($refundRequest, $request->user(), $data['message'] ?? null);

        return ApiResponse::success([
            'id' => $refund->id,
            'status' => $refund->status,
        ]);
    }
}
