<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Models\User;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;

class MessageEmailService
{
    private const DELAY_MINUTES = 5;

    public function __construct(
        private readonly TransactionalEmailService $email,
    ) {}

    public function scheduleIfNeeded(Message $message, Conversation $conversation): ?EmailJob
    {
        $message->loadMissing(['sender', 'receiver']);
        $conversation->loadMissing(['sender', 'receiver']);

        $body = trim((string) $message->message);
        if ($body === '') {
            return null;
        }

        $receiver = $message->receiver;
        if ($receiver === null || ! $this->receiverWantsEmail($receiver)) {
            return null;
        }

        $to = trim((string) ($receiver->email ?? ''));
        if ($to === '') {
            return null;
        }

        $scheduledAt = now()->addMinutes(self::DELAY_MINUTES);
        $templateData = $this->templateData($message, $conversation);
        $metadata = [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'receiver_id' => $receiver->id,
        ];

        $existing = EmailJob::query()
            ->where('status', 'pending')
            ->where('email_type', TransactionalEmailType::NEW_MESSAGE)
            ->where('to_email', $to)
            ->where('metadata->conversation_id', $conversation->id)
            ->where('metadata->receiver_id', $receiver->id)
            ->orderByDesc('id')
            ->first();

        if ($existing !== null) {
            $existing->update([
                'subject' => 'You have a new message',
                'template' => 'new-message',
                'template_data' => $templateData,
                'scheduled_at' => $scheduledAt,
                'metadata' => array_merge($existing->metadata ?? [], $metadata, [
                    'type' => TransactionalEmailType::NEW_MESSAGE,
                ]),
            ]);

            return $existing->fresh();
        }

        return $this->email->queue(
            TransactionalEmailType::NEW_MESSAGE,
            $to,
            $templateData,
            subject: 'You have a new message',
            template: 'new-message',
            scheduledAt: $scheduledAt,
            metadata: $metadata,
        );
    }

    private function receiverWantsEmail(User $receiver): bool
    {
        return (bool) ($receiver->send_email_new_message ?? true);
    }

    /**
     * @return array<string, mixed>
     */
    private function templateData(Message $message, Conversation $conversation): array
    {
        $sender = $message->sender;
        $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

        return [
            'title' => 'You have a new message',
            'messageSender' => $this->senderLabel($sender),
            'messageSubject' => (string) ($conversation->subject ?? ''),
            'messageText' => (string) $message->message,
            'url' => "{$base}/messages",
            'buttonText' => 'Messages',
        ];
    }

    private function senderLabel(?User $sender): string
    {
        if ($sender === null) {
            return 'A user';
        }

        $username = trim((string) ($sender->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        $name = trim((string) ($sender->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim((string) ($sender->email ?? 'A user'));
    }
}
