<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Models\User;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Modules\Selloff\Order\Models\RefundRequest;

class RefundEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
    ) {}

    public function queueSubmitted(RefundRequest $refund): ?EmailJob
    {
        $refund->loadMissing(['seller', 'order']);
        $sellerEmail = trim((string) ($refund->seller?->email ?? ''));

        if ($sellerEmail === '') {
            return null;
        }

        return $this->queueMain(
            TransactionalEmailType::REFUND_SUBMITTED,
            $sellerEmail,
            'Refund request',
            'A buyer submitted a refund request for order #'.e((string) ($refund->order?->order_number ?? $refund->order_number)).'.',
            $this->vendorRefundUrl($refund),
            'See details',
        );
    }

    public function queueApproved(RefundRequest $refund, User $recipient): ?EmailJob
    {
        return $this->queueStatusUpdate(
            $refund,
            $recipient,
            TransactionalEmailType::REFUND_APPROVED,
            'Your refund request has been approved.',
        );
    }

    public function queueRejected(RefundRequest $refund, User $recipient): ?EmailJob
    {
        return $this->queueStatusUpdate(
            $refund,
            $recipient,
            TransactionalEmailType::REFUND_REJECTED,
            'Your refund request has been updated.',
        );
    }

    public function queueMessage(RefundRequest $refund, User $recipient): ?EmailJob
    {
        $email = trim((string) ($recipient->email ?? ''));

        if ($email === '') {
            return null;
        }

        $refund->loadMissing(['order']);

        return $this->queueMain(
            TransactionalEmailType::REFUND_MESSAGE,
            $email,
            'Refund request',
            'There is a new update on your refund request for order #'.e((string) ($refund->order?->order_number ?? $refund->order_number)).'.',
            $this->refundUrlFor($recipient, $refund),
            'See details',
        );
    }

    private function queueStatusUpdate(
        RefundRequest $refund,
        User $recipient,
        string $type,
        string $content,
    ): ?EmailJob {
        $email = trim((string) ($recipient->email ?? ''));

        if ($email === '') {
            return null;
        }

        $refund->loadMissing(['order']);

        return $this->queueMain(
            $type,
            $email,
            'Refund request',
            $content,
            $this->refundUrlFor($recipient, $refund),
            'See details',
        );
    }

    private function queueMain(
        string $type,
        string $to,
        string $subject,
        string $content,
        string $url,
        string $buttonText,
    ): ?EmailJob {
        return $this->email->queue(
            $type,
            $to,
            [
                'title' => $subject,
                'content' => $content,
                'url' => $url,
                'buttonText' => $buttonText,
            ],
            subject: $subject,
            template: 'main',
        );
    }

    private function refundUrlFor(User $recipient, RefundRequest $refund): string
    {
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        if ($recipient->can('vendor') && (int) $refund->seller_id === (int) $recipient->id) {
            return "{$base}/vendor/refunds";
        }

        return "{$base}/account/refunds";
    }

    private function vendorRefundUrl(RefundRequest $refund): string
    {
        return rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/vendor/refunds';
    }
}
