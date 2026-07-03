<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Cart\Services\CommerceGtmService;
use App\Modules\Selloff\Order\Http\Resources\Api\V1\OrderResource;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Services\BuyerCancelOrderService;
use App\Modules\Selloff\Order\Services\BuyerOrderInvoiceService;
use App\Support\ApiResponse;
use App\Support\Gtm\OrderGtmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['items', 'buyer'])
            ->where('buyer_id', $request->user()->id)
            ->orderByDesc('id');

        $paginator = $query->paginate(min($request->integer('per_page', 15), 100));
        $paginator->through(fn (Order $order) => new OrderResource($order));

        return ApiResponse::success($paginator);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless(
            (int) $order->buyer_id === (int) $request->user()->id || $request->user()->can('admin_panel'),
            403,
        );

        $order->load(['items.product', 'buyer']);

        return ApiResponse::success(new OrderResource($order));
    }

    public function cancel(Request $request, Order $order, BuyerCancelOrderService $service): JsonResponse
    {
        $order = $service->cancel($order, $request->user());

        return ApiResponse::success(new OrderResource($order->load(['items.product', 'buyer'])));
    }

    public function invoice(Request $request, Order $order, BuyerOrderInvoiceService $service): JsonResponse
    {
        return ApiResponse::success($service->build($order, $request->user(), 'buyer'));
    }

    public function gtmEvents(Request $request, Order $order, OrderGtmService $orderGtm): JsonResponse
    {
        abort_unless(
            (int) $order->buyer_id === (int) $request->user()->id,
            403,
        );

        $events = $orderGtm->consumeStoredEvents($order);

        if ($events === [] && $order->payment_status === 'payment_received') {
            $checkout = \App\Modules\Selloff\Order\Models\CheckoutSession::query()
                ->where('checkout_token', $order->checkout_token)
                ->first();
            if ($checkout) {
                $events = app(CommerceGtmService::class)->purchase($order, $checkout, $request->user());
                $orderGtm->storePendingEvents($order, $events);
                $events = $orderGtm->consumeStoredEvents($order);
            }
        }

        return ApiResponse::success(['gtm_events' => $events]);
    }
}
