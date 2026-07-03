<?php

namespace App\Modules\Selloff\Order\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Services\BuyerOrderInvoiceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderInvoiceController extends Controller
{
    public function showByOrderNumber(Request $request, int $orderNumber, BuyerOrderInvoiceService $service): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer !== null, 401);

        $type = $request->string('type', 'buyer')->toString();
        if (! in_array($type, ['admin', 'buyer', 'seller'], true)) {
            abort(422, 'Invalid invoice type.');
        }

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();

        if ($type === 'admin') {
            abort_unless($viewer->can('admin_panel'), 403);
        } elseif ($type === 'buyer') {
            abort_unless((int) $order->buyer_id === (int) $viewer->id, 403);
        } else {
            abort_unless(
                $viewer->can('vendor') && $order->items()->where('seller_id', $viewer->id)->exists(),
                403,
            );
        }

        return ApiResponse::success($service->build($order, $viewer, $type));
    }
}
