<?php

namespace App\Modules\Selloff\Notification\Support;

use App\Models\User;
use App\Modules\Selloff\Messaging\Models\Message;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\User\Services\UserPresenceService;

class DelayedMessageEmailGuard
{
    public function __construct(
        private readonly UserPresenceService $presence,
    ) {}

    public function shouldSkipDelivery(EmailJob $job): ?string
    {
        if (($job->email_type ?? $job->metadata['type'] ?? null) !== TransactionalEmailType::NEW_MESSAGE) {
            return null;
        }

        $metadata = is_array($job->metadata) ? $job->metadata : [];
        $messageId = (int) ($metadata['message_id'] ?? 0);
        $receiverId = (int) ($metadata['receiver_id'] ?? 0);

        if ($messageId <= 0 || $receiverId <= 0) {
            return 'missing_message_metadata';
        }

        $message = Message::query()->find($messageId);
        if ($message === null) {
            return 'message_missing';
        }

        if ($message->is_read) {
            return 'message_already_read';
        }

        $receiver = User::query()->find($receiverId);
        if ($receiver === null) {
            return 'receiver_missing';
        }

        if (! (bool) ($receiver->send_email_new_message ?? true)) {
            return 'receiver_opted_out';
        }

        if ($this->presence->isOnline($receiver)) {
            return 'receiver_online';
        }

        return null;
    }
}
