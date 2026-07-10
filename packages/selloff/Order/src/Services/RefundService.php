<?php

namespace App\Modules\Selloff\Order\Services;

use App\Models\User;
use App\Modules\Selloff\Notification\Services\RefundEmailService;
use App\Modules\Selloff\Order\Models\DigitalSale;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;
use App\Modules\Selloff\Order\Models\RefundMessage;
use App\Modules\Selloff\Order\Models\RefundRequest;
use App\Modules\Selloff\Payout\Services\VendorEarningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(
        private readonly VendorEarningService $vendorEarnings,
        private readonly RefundEmailService $emails,
    ) {}

    public function createForOrder(Order $order, User $initiator, ?string $description = null): RefundRequest
    {
        if (! in_array($order->payment_status, ['payment_received', 'awaiting_payment'], true)) {
            throw ValidationException::withMessages([
                'order' => ['Order is not eligible for refund.'],
            ]);
        }

        $sellerId = $order->items()->value('seller_id');
        $orderItemId = $order->items()->count() === 1 ? $order->items()->value('id') : null;

        $refund = RefundRequest::query()->create([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_item_id' => $orderItemId,
            'buyer_id' => $order->buyer_id,
            'seller_id' => $sellerId,
            'description' => $description,
            'status' => 'pending',
            'is_completed' => false,
        ]);

        $this->emails->queueSubmitted($refund->load(['seller', 'order']));

        return $refund;
    }

    public function vendorApprove(RefundRequest $refundRequest, User $seller, ?string $message = null): RefundRequest
    {
        abort_unless((int) $refundRequest->seller_id === (int) $seller->id, 403);

        if ($refundRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Refund request is not pending.'],
            ]);
        }

        $refundRequest->update(['status' => 'approved']);

        if ($message) {
            RefundMessage::query()->create([
                'refund_request_id' => $refundRequest->id,
                'user_id' => $seller->id,
                'message' => $message,
                'is_admin' => false,
            ]);
        }

        $fresh = $refundRequest->fresh()->load(['order', 'buyer', 'seller']);
        if ($refundRequest->buyer) {
            $this->emails->queueApproved($fresh, $refundRequest->buyer);
        }

        return $fresh;
    }

    public function vendorReject(RefundRequest $refundRequest, User $seller, ?string $message = null): RefundRequest
    {
        abort_unless((int) $refundRequest->seller_id === (int) $seller->id, 403);

        return $this->reject($refundRequest, $seller, $message, false);
    }

    public function approve(RefundRequest $refundRequest, User $admin, ?string $message = null): RefundRequest
    {
        return $this->completeAdminRefund($refundRequest, $admin, $message);
    }

    public function completeAdminRefund(RefundRequest $refundRequest, User $admin, ?string $message = null): RefundRequest
    {
        if ($refundRequest->is_completed) {
            throw ValidationException::withMessages([
                'status' => ['Refund has already been completed.'],
            ]);
        }

        return DB::transaction(function () use ($refundRequest, $admin, $message): RefundRequest {
            $order = $refundRequest->order()->with(['items.product'])->firstOrFail();
            $lineItem = $this->resolveRefundLineItem($refundRequest, $order);

            $lineItem->update(['order_status' => 'refund_approved']);

            $earning = $this->vendorEarnings->findForOrderItem($lineItem, $order);

            if (
                $earning
                && ! in_array($order->payment_method, ['cash_on_delivery', 'cod'], true)
            ) {
                $seller = User::query()->find($lineItem->seller_id);
                if ($seller) {
                    $this->vendorEarnings->reverseEarningForRefund($earning, $seller);
                }
            }

            if ($lineItem->product_type === 'digital' && $lineItem->product_id) {
                DigitalSale::query()
                    ->where('order_id', $order->id)
                    ->where('product_id', $lineItem->product_id)
                    ->where('buyer_id', $order->buyer_id)
                    ->delete();
            }

            $refundRequest->update([
                'status' => 'approved',
                'is_completed' => true,
                'order_item_id' => $lineItem->id,
            ]);

            $order->touch();

            if ($message) {
                RefundMessage::query()->create([
                    'refund_request_id' => $refundRequest->id,
                    'user_id' => $admin->id,
                    'message' => $message,
                    'is_admin' => true,
                ]);
            }

            $fresh = $refundRequest->fresh()->load(['order.items', 'buyer', 'seller', 'orderItem']);
            if ($refundRequest->buyer) {
                $this->emails->queueApproved($fresh, $refundRequest->buyer);
            }

            return $fresh;
        });
    }

    private function resolveRefundLineItem(RefundRequest $refundRequest, Order $order): OrderItem
    {
        if ($refundRequest->order_item_id) {
            $lineItem = $order->items->firstWhere('id', $refundRequest->order_item_id);
            if ($lineItem) {
                return $lineItem;
            }
        }

        $fallback = $order->items->first();
        if ($fallback === null) {
            throw ValidationException::withMessages([
                'order_item_id' => ['Refund line item was not found on this order.'],
            ]);
        }

        return $fallback;
    }

    public function reject(RefundRequest $refundRequest, User $admin, ?string $message = null, bool $isAdmin = true): RefundRequest
    {
        if ($refundRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => ['Refund request is not pending.'],
            ]);
        }

        $refundRequest->update(['status' => 'rejected']);

        if ($message) {
            RefundMessage::query()->create([
                'refund_request_id' => $refundRequest->id,
                'user_id' => $admin->id,
                'message' => $message,
                'is_admin' => $isAdmin,
            ]);
        }

        $fresh = $refundRequest->fresh()->load(['order', 'buyer', 'seller']);
        if ($refundRequest->buyer) {
            $this->emails->queueRejected($fresh, $refundRequest->buyer);
        }

        return $fresh;
    }
}
