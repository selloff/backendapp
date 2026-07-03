<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\OrderResource;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Services\VendorOrderService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $sellerId = $request->user()->id;

        $listSt = $request->filled('st') ? $request->string('st') : null;

        $query = Order::query()
            ->with(['items', 'buyer'])
            ->whereHas('items', fn (Builder $q) => $q->where('seller_id', $sellerId))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($listSt === 'completed', fn (Builder $q) => $q->where('status', 'completed'))
            ->when($listSt === 'cancelled', fn (Builder $q) => $q->where('status', 'cancelled'))
            ->when($listSt === 'active', fn (Builder $q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->orderByDesc('id');

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));
        $paginator->through(fn (Order $order) => new OrderResource($order));

        return ApiResponse::success($paginator);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $sellerId = $request->user()->id;

        abort_unless(
            $order->items()->where('seller_id', $sellerId)->exists(),
            404,
        );

        $order->load(['items', 'buyer']);

        return ApiResponse::success(new OrderResource($order));
    }

    public function updateStatus(Request $request, Order $order, VendorOrderService $orders): JsonResponse
    {
        abort_unless($request->user()->can('vendor'), 403);

        $data = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $orders->updateStatus(
            $order,
            $request->user(),
            $data['status'],
            $data['tracking_number'] ?? null,
        );

        return ApiResponse::success(new OrderResource($updated));
    }
}
