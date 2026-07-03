<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Order\Services\RefundService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = RefundRequest::query()
            ->with(['order', 'seller'])
            ->where('buyer_id', $request->user()->id)
            ->orderByDesc('id');

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));

        return ApiResponse::success($paginator);
    }

    public function store(Request $request, Order $order, RefundService $refunds): JsonResponse
    {
        abort_unless((int) $order->buyer_id === (int) $request->user()->id, 403);

        $data = $request->validate(['description' => ['nullable', 'string', 'max:5000']]);

        $refund = $refunds->createForOrder($order, $request->user(), $data['description'] ?? null);

        return ApiResponse::success([
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'status' => $refund->status,
            'description' => $refund->description,
            'created_at' => $refund->created_at,
        ], 201);
    }
}
