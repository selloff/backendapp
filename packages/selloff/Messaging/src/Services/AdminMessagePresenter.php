<?php

namespace App\Modules\Selloff\Messaging\Services;

use App\Models\User;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;

class AdminMessagePresenter
{
    /**
     * @return array{buyer: User|null, vendor: User|null, vendor_id: int|null}
     */
    public function resolveParticipants(Conversation $conversation): array
    {
        $vendorId = $conversation->product?->vendor_id;
        $sender = $conversation->relationLoaded('sender') ? $conversation->sender : null;
        $receiver = $conversation->relationLoaded('receiver') ? $conversation->receiver : null;

        if ($vendorId === null) {
            return [
                'buyer' => $sender,
                'vendor' => $receiver,
                'vendor_id' => null,
            ];
        }

        if ($sender?->id === $vendorId) {
            return ['buyer' => $receiver, 'vendor' => $sender, 'vendor_id' => $vendorId];
        }

        if ($receiver?->id === $vendorId) {
            return ['buyer' => $sender, 'vendor' => $receiver, 'vendor_id' => $vendorId];
        }

        return [
            'buyer' => $sender,
            'vendor' => $receiver,
            'vendor_id' => $vendorId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatConversationListItem(Conversation $conversation): array
    {
        $participants = $this->resolveParticipants($conversation);
        $latestMessage = $conversation->relationLoaded('latestMessage') ? $conversation->latestMessage : null;

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'product_id' => $conversation->product_id,
            'product_slug' => $conversation->product?->slug,
            'product_title' => $conversation->product?->translations?->first()?->title,
            'is_flagged' => (bool) $conversation->is_flagged,
            'buyer' => $this->formatUser($participants['buyer']),
            'vendor' => $this->formatUser($participants['vendor']),
            'sender' => $this->formatUser($conversation->sender),
            'receiver' => $this->formatUser($conversation->receiver),
            'message_count' => (int) ($conversation->messages_count ?? $conversation->messages()->count()),
            'last_message_preview' => $latestMessage?->message,
            'last_message_at' => $conversation->last_message_at,
            'updated_at' => $conversation->updated_at,
            'created_at' => $conversation->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatConversationDetail(Conversation $conversation): array
    {
        $participants = $this->resolveParticipants($conversation);

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'is_flagged' => (bool) $conversation->is_flagged,
            'product_id' => $conversation->product_id,
            'product_slug' => $conversation->product?->slug,
            'product_title' => $conversation->product?->translations?->first()?->title,
            'buyer' => $this->formatUser($participants['buyer']),
            'vendor' => $this->formatUser($participants['vendor']),
            'sender' => $this->formatUser($conversation->sender),
            'receiver' => $this->formatUser($conversation->receiver),
            'created_at' => $conversation->created_at,
            'last_message_at' => $conversation->last_message_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatMessage(Message $message, ?int $buyerId, ?int $vendorId): array
    {
        $role = null;
        if ($message->sender_id === $vendorId) {
            $role = 'vendor';
        } elseif ($message->sender_id === $buyerId) {
            $role = 'buyer';
        }

        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'sender' => $this->formatUser($message->relationLoaded('sender') ? $message->sender : null),
            'role' => $role,
            'message' => $message->message,
            'is_read' => $message->is_read,
            'created_at' => $message->created_at,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
        ];
    }
}
