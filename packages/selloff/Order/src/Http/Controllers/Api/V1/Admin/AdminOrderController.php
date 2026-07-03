<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\OrderResource;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Order\Services\AdminOrderExportService;
use App\Modules\Selloff\Order\Services\AdminOrderService;
use App\Modules\Selloff\Order\Services\BuyerOrderInvoiceService;
use App\Modules\Selloff\Order\Services\OrderFulfillmentService;
use App\Modules\Selloff\Order\Services\RefundService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['items', 'buyer'])
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn (Builder $q) => $q->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = $request->string('q')->toString();
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('order_number', 'like', '%'.$term.'%');
                    if (ctype_digit($term)) {
                        $inner->orWhere('id', (int) $term);
                    }
                });
            })
            ->orderByDesc('id');

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));
        $paginator->through(fn (Order $order) => new OrderResource($order));

        return ApiResponse::success($paginator);
    }

    public function export(Request $request, AdminOrderExportService $export): StreamedResponse
    {
        $format = $request->string('format')->toString();
        if ($format !== '' && ! in_array($format, ['csv', 'xml', 'excel', 'xlsx'], true)) {
            abort(422, 'Invalid export format.');
        }

        return $export->export($request);
    }

    public function invoice(Request $request, Order $order, BuyerOrderInvoiceService $service): JsonResponse
    {
        return ApiResponse::success($service->build($order, $request->user(), 'admin'));
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'items.product.images',
            'items.seller',
            'buyer',
            'paymentTransaction',
        ]);

        return ApiResponse::success(new OrderResource($order));
    }

    public function storeRefund(Request $request, Order $order, RefundService $refunds): JsonResponse
    {
        $data = $request->validate(['description' => ['nullable', 'string', 'max:5000']]);

        $refund = $refunds->createForOrder($order, $request->user(), $data['description'] ?? null);

        return ApiResponse::success([
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'status' => $refund->status,
        ], 201);
    }

    public function markPaid(Order $order, AdminOrderService $orders, OrderFulfillmentService $fulfillment): JsonResponse
    {
        $updated = $orders->markPaid($order, $fulfillment);

        return ApiResponse::success(new OrderResource($updated));
    }

    public function cancel(Order $order, AdminOrderService $orders): JsonResponse
    {
        $updated = $orders->cancel($order);

        return ApiResponse::success(new OrderResource($updated));
    }

    public function updateItemStatus(Request $request, Order $order, OrderItem $item, AdminOrderService $orders): JsonResponse
    {
        $data = $request->validate(['order_status' => ['required', 'string', 'max:50']]);
        $updated = $orders->updateItemStatus($order, $item, $data['order_status']);

        return ApiResponse::success([
            'id' => $updated->id,
            'order_status' => $updated->order_status,
        ]);
    }

    public function approveGuestItem(Order $order, OrderItem $item, AdminOrderService $orders): JsonResponse
    {
        $updated = $orders->approveGuestItem($order, $item);

        return ApiResponse::success([
            'id' => $updated->id,
            'is_approved' => $updated->is_approved,
        ]);
    }

    public function destroyItem(Order $order, OrderItem $item, AdminOrderService $orders): JsonResponse
    {
        $orders->deleteItem($order, $item);

        return ApiResponse::success(null, message: 'Order item deleted.');
    }
}
