<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\ProductMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Modules\Selloff\Support\Models\ContactMessage;

class ContactEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly ProductMailViewDataFactory $viewData,
    ) {}

    public function queueAdminAlert(ContactMessage $contactMessage): ?EmailJob
    {
        $to = $this->viewData->adminRecipient();

        if ($to === null) {
            return null;
        }

        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return $this->email->queue(
            TransactionalEmailType::CONTACT_MESSAGE,
            $to,
            [
                'title' => 'Contact message',
                'senderName' => (string) $contactMessage->name,
                'senderEmail' => (string) $contactMessage->email,
                'subjectLine' => (string) ($contactMessage->subject ?? ''),
                'messageText' => (string) $contactMessage->message,
                'url' => "{$base}/admin/contact",
                'buttonText' => 'View messages',
            ],
            subject: 'Contact message',
            template: 'contact-message',
        );
    }
}
