<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\ProductMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\User\Models\VendorProfile;

class VendorFeedbackEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly ProductMailViewDataFactory $viewData,
    ) {}

    public function queueReceived(Feedback $feedback): ?EmailJob
    {
        $feedback->loadMissing(['vendor', 'user']);
        $to = trim((string) ($feedback->vendor?->email ?? ''));

        if ($to === '') {
            return null;
        }

        $author = $feedback->user;
        $shopName = VendorProfile::query()
            ->where('user_id', $feedback->vendor_id)
            ->value('shop_name') ?? $feedback->vendor?->name ?? 'your shop';

        return $this->email->queue(
            TransactionalEmailType::VENDOR_FEEDBACK_RECEIVED,
            $to,
            [
                'title' => 'New seller feedback received',
                'shopName' => (string) $shopName,
                'authorName' => trim((string) ($author?->name ?? 'A buyer')),
                'authorUsername' => trim((string) ($author?->username ?? $author?->slug ?? '')),
                'authorEmail' => trim((string) ($author?->email ?? '')),
                'authorPhone' => trim((string) ($author?->phone_number ?? '')),
                'feedbackType' => ucfirst((string) $feedback->feedback_type),
                'feedbackContent' => (string) $feedback->feedback,
                'rating' => $feedback->rating,
                'url' => rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/vendor/feedback',
                'buttonText' => 'View feedback',
            ],
            subject: "New seller feedback — {$shopName}",
            template: 'feedback-message',
        );
    }

    public function sendApproved(Feedback $feedback): void
    {
        $feedback->loadMissing(['vendor', 'user']);

        $to = trim((string) ($feedback->vendor?->email ?? ''));

        if ($to === '') {
            return;
        }

        $shopName = VendorProfile::query()
            ->where('user_id', $feedback->vendor_id)
            ->value('shop_name') ?? $feedback->vendor?->name ?? 'your shop';

        $authorName = $feedback->user?->name ?? 'A buyer';
        $type = ucfirst((string) $feedback->feedback_type);
        $rating = $feedback->rating !== null ? " ({$feedback->rating}/5 stars)" : '';
        $content = "New seller feedback has been approved on your Selloff shop.<br><br>"
            ."<strong>From:</strong> {$authorName}<br>"
            ."<strong>Type:</strong> {$type}{$rating}<br><br>"
            .'"'.e((string) $feedback->feedback).'"';

        $this->email->sendNow(
            TransactionalEmailType::VENDOR_FEEDBACK_APPROVED,
            $to,
            [
                'title' => 'Seller feedback approved',
                'content' => $content,
                'url' => rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/vendor/feedback',
                'buttonText' => 'View feedback',
            ],
            subject: "New seller feedback — {$shopName}",
            template: 'main',
        );
    }
}
