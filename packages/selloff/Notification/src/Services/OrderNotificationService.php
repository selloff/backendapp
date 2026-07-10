<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\OrderMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Modules\Selloff\Order\Models\Order;
use App\Modules\Selloff\Order\Models\OrderItem;

class OrderNotificationService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly OrderMailViewDataFactory $viewData,
    ) {}

    public function queueOrderConfirmation(Order $order): ?EmailJob
    {
        return $this->queueOrderPlaced($order);
    }

    public function queueOrderPlaced(Order $order): ?EmailJob
    {
        $order->loadMissing(['items.seller', 'buyer']);

        $buyerJob = null;
        $buyerEmail = $this->viewData->buyerEmail($order);

        if ($buyerEmail !== null) {
            $buyerJob = $this->email->queue(
                TransactionalEmailType::NEW_ORDER,
                $buyerEmail,
                $this->viewData->forBuyerOrder($order),
                subject: 'Thank you for your order',
                template: 'new-order',
            );
        }

        $notifiedSellers = [];

        foreach ($order->items as $item) {
            $sellerId = (int) $item->seller_id;

            if (in_array($sellerId, $notifiedSellers, true)) {
                continue;
            }

            $notifiedSellers[] = $sellerId;
            $sellerEmail = trim((string) ($item->seller?->email ?? ''));

            if ($sellerEmail === '') {
                continue;
            }

            $this->email->queue(
                TransactionalEmailType::NEW_ORDER_SELLER,
                $sellerEmail,
                $this->viewData->forSellerOrder($order, $sellerId),
                subject: 'You have a new order',
                template: 'new-order-seller',
            );
        }

        return $buyerJob;
    }

    public function queueOrderShipped(
        Order $order,
        int $sellerId,
        ?string $trackingNumber = null,
        ?string $trackingUrl = null,
    ): ?EmailJob {
        $order->loadMissing(['items', 'buyer']);
        $buyerEmail = $this->viewData->buyerEmail($order);

        if ($buyerEmail === null) {
            return null;
        }

        $item = $order->items->firstWhere('seller_id', $sellerId);

        if ($item === null) {
            return null;
        }

        return $this->email->queue(
            TransactionalEmailType::ORDER_SHIPPED,
            $buyerEmail,
            $this->viewData->forShippedOrder($order, $item, $trackingNumber, $trackingUrl),
            subject: 'Your order has shipped',
            template: 'order-shipped',
        );
    }
}
