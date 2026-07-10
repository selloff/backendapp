<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Models\User;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\ProductMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;

class ShopOpeningEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly ProductMailViewDataFactory $viewData,
    ) {}

    /**
     * @return list<EmailJob|null>
     */
    public function queueSubmitted(User $user): array
    {
        return [
            $this->queueApplicantSubmitted($user),
            $this->queueAdminAlert($user),
        ];
    }

    public function queueApproved(User $user): ?EmailJob
    {
        return $this->queueApplicantStatus(
            $user,
            TransactionalEmailType::SHOP_OPENING_APPROVED,
            'Your shop opening request was approved',
            'Your shop opening request has been approved. You can now start selling on Selloff.',
            rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/vendor/products/new',
            'Start selling',
        );
    }

    public function queueRejected(User $user, int $status, ?string $reason): ?EmailJob
    {
        $content = $status === 3
            ? 'Your shop opening request was permanently rejected.'
            : 'Your shop opening request was rejected.';

        if (filled($reason)) {
            $content .= '<br><br><strong>Reason:</strong><br>'.e($reason);
        }

        return $this->queueApplicantStatus(
            $user,
            TransactionalEmailType::SHOP_OPENING_REJECTED,
            'Shop opening request update',
            $content,
            rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/start-selling',
            'See details',
        );
    }

    private function queueApplicantSubmitted(User $user): ?EmailJob
    {
        return $this->queueApplicantStatus(
            $user,
            TransactionalEmailType::SHOP_OPENING_SUBMITTED,
            'Shop opening request received',
            'Thank you for submitting your shop opening request. Our team will review it and get back to you shortly.',
            rtrim((string) config('selloff.spa_url', config('app.url')), '/').'/start-selling',
            'See details',
        );
    }

    private function queueAdminAlert(User $user): ?EmailJob
    {
        $to = $this->viewData->adminRecipient();

        if ($to === null) {
            return null;
        }

        $username = trim((string) ($user->username ?? $user->slug ?? $user->email ?? 'A user'));
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return $this->email->queue(
            TransactionalEmailType::SHOP_OPENING_ADMIN_ALERT,
            $to,
            [
                'title' => 'New shop opening request',
                'content' => "There is a new shop opening request.<br><br>User: <strong>{$username}</strong>",
                'url' => "{$base}/admin/shop-opening",
                'buttonText' => 'View details',
            ],
            subject: 'Shop opening request',
            template: 'main',
        );
    }

    private function queueApplicantStatus(
        User $user,
        string $type,
        string $subject,
        string $content,
        string $url,
        string $buttonText,
    ): ?EmailJob {
        $to = trim((string) ($user->email ?? ''));

        if ($to === '') {
            return null;
        }

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
}
