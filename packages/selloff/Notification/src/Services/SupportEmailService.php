<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\ProductMailViewDataFactory;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Modules\Selloff\Support\Models\SupportMessage;
use App\Modules\Selloff\Support\Models\SupportTicket;

class SupportEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly ProductMailViewDataFactory $viewData,
    ) {}

    /**
     * @return list<EmailJob|null>
     */
    public function queueTicketOpened(SupportTicket $ticket, SupportMessage $message): array
    {
        $ticket->loadMissing('user');
        $jobs = [];

        $jobs[] = $this->queueAdminNewTicket($ticket, $message);
        $jobs[] = $this->queueUserTicketReceived($ticket);

        return $jobs;
    }

    public function queueUserReply(SupportTicket $ticket, SupportMessage $message): ?EmailJob
    {
        return $this->queueAdminNewTicket($ticket, $message, isReply: true);
    }

    public function queueAdminReply(SupportTicket $ticket): ?EmailJob
    {
        $ticket->loadMissing('user');
        $to = trim((string) ($ticket->user?->email ?? ''));

        if ($to === '') {
            return null;
        }

        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return $this->email->queue(
            TransactionalEmailType::SUPPORT_REPLY,
            $to,
            [
                'title' => 'Support message replied',
                'content' => 'Our support team replied to your ticket. Sign in to read the full response.',
                'url' => "{$base}/support/tickets/{$ticket->id}",
                'buttonText' => 'View ticket',
            ],
            subject: "Support reply — ticket #{$ticket->id}",
            template: 'main',
        );
    }

    private function queueAdminNewTicket(
        SupportTicket $ticket,
        SupportMessage $message,
        bool $isReply = false,
    ): ?EmailJob {
        $to = $this->viewData->adminRecipient();

        if ($to === null) {
            return null;
        }

        $ticket->loadMissing('user');
        $user = $ticket->user;
        $username = trim((string) ($user?->username ?? $user?->slug ?? $user?->name ?? 'User'));
        $userEmail = trim((string) ($user?->email ?? ''));
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        $title = $isReply ? 'New support message' : 'New support ticket';
        $content = "<strong>User:</strong> {$username}<br>"
            ."<strong>Email:</strong> {$userEmail}<br>"
            ."<strong>Subject:</strong> ".e((string) $ticket->subject)."<br><br>"
            .e((string) $message->message);

        return $this->email->queue(
            TransactionalEmailType::SUPPORT_TICKET,
            $to,
            [
                'title' => $title,
                'content' => $content,
                'url' => "{$base}/admin/support/tickets/{$ticket->id}",
                'buttonText' => 'View ticket',
            ],
            subject: "{$title} #{$ticket->id}",
            template: 'main',
        );
    }

    private function queueUserTicketReceived(SupportTicket $ticket): ?EmailJob
    {
        $ticket->loadMissing('user');
        $to = trim((string) ($ticket->user?->email ?? ''));

        if ($to === '') {
            return null;
        }

        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return $this->email->queue(
            TransactionalEmailType::SUPPORT_TICKET,
            $to,
            [
                'title' => 'Support ticket received',
                'content' => 'We received your support request and will get back to you shortly.',
                'url' => "{$base}/support/tickets/{$ticket->id}",
                'buttonText' => 'View ticket',
            ],
            subject: "Support ticket received — #{$ticket->id}",
            template: 'main',
        );
    }
}
