<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\ProductMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;

class ProductModerationEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly ProductMailViewDataFactory $viewData,
    ) {}

    public function queueApproved(Product $product): ?EmailJob
    {
        $vendor = $product->vendor;
        $to = trim((string) ($vendor?->email ?? ''));

        if ($to === '') {
            return null;
        }

        $data = $this->viewData->forProduct($product);
        $subject = "Your item: {$data['productTitle']} has been approved.";

        return $this->email->queue(
            TransactionalEmailType::PRODUCT_APPROVED,
            $to,
            [
                ...$data,
                'subject' => $subject,
            ],
            subject: $subject,
            template: 'item-approved',
        );
    }

    public function queueRejected(Product $product, string $reason): ?EmailJob
    {
        $vendor = $product->vendor;
        $to = trim((string) ($vendor?->email ?? ''));

        if ($to === '') {
            return null;
        }

        $data = $this->viewData->forProduct($product, $reason);
        $subject = "Your ad was rejected: {$data['productTitle']}";

        return $this->email->queue(
            TransactionalEmailType::PRODUCT_REJECTED,
            $to,
            [
                ...$data,
                'subject' => $subject,
            ],
            subject: $subject,
            template: 'item-rejected',
        );
    }

    public function queueEditRejected(Product $product, string $reason): ?EmailJob
    {
        $vendor = $product->vendor;
        $to = trim((string) ($vendor?->email ?? ''));

        if ($to === '') {
            return null;
        }

        $data = $this->viewData->forProduct($product, $reason);
        $subject = "Your product edit was rejected: {$data['productTitle']}";

        return $this->email->queue(
            TransactionalEmailType::PRODUCT_REJECTED,
            $to,
            [
                ...$data,
                'subject' => $subject,
                'body' => "Your recent changes to {$data['productTitle']} were not approved. {$reason} Please update your listing and submit again.",
            ],
            subject: $subject,
            template: 'item-rejected',
        );
    }
}
