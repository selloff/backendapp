<?php

namespace App\Modules\Selloff\Messaging\Services;

use App\Models\User;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use App\Modules\Selloff\Notification\Services\MessageEmailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MessageService
{
    public function __construct(
        private readonly MessageEmailService $messageEmails,
    ) {}

    public function conversationsForUser(User $user): Collection
    {
        return Conversation::query()
            ->with(['sender', 'receiver', 'product.translations'])
            ->where(fn (Builder $q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Conversation $conversation) => $this->formatConversation($conversation, $user));
    }

    public function unreadCount(User $user): int
    {
        return Message::query()
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    public function messagesForConversation(int $conversationId, User $user, ?int $afterMessageId = null): Collection
    {
        $conversation = $this->findConversationForUser($conversationId, $user);

        $query = $conversation->messages()->with(['sender', 'receiver'])->orderBy('id');

        if ($afterMessageId !== null) {
            $query->where('messages.id', '>', $afterMessageId);
        }

        return $query->get()->map(fn (Message $message) => $this->formatMessage($message));
    }

    public function markConversationRead(int $conversationId, User $user): void
    {
        $conversation = $this->findConversationForUser($conversationId, $user);

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function sendToConversation(int $conversationId, User $user, string $body): Message
    {
        $conversation = $this->findConversationForUser($conversationId, $user);
        $receiverId = $conversation->sender_id === $user->id
            ? $conversation->receiver_id
            : $conversation->sender_id;

        return $this->storeMessage($conversation, $user->id, $receiverId, $body);
    }

    public function sendNewConversation(
        User $sender,
        int $receiverId,
        string $message,
        ?string $subject = null,
        ?int $productId = null,
    ): Message {
        $conversation = Conversation::query()->firstOrCreate(
            [
                'sender_id' => $sender->id,
                'receiver_id' => $receiverId,
                'product_id' => $productId,
            ],
            [
                'subject' => $subject,
                'last_message_at' => now(),
            ],
        );

        return $this->storeMessage($conversation, $sender->id, $receiverId, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function messagePayload(Message $message, MessageGtmService $gtm): array
    {
        return [
            ...$this->formatMessage($message),
            'gtm_events' => $gtm->eventsForMessage($message),
        ];
    }

    private function storeMessage(Conversation $conversation, int $senderId, int $receiverId, string $body): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $body,
            'is_read' => false,
        ]);

        $conversation->update(['last_message_at' => now()]);

        $message = $message->load(['sender', 'receiver']);
        $this->messageEmails->scheduleIfNeeded($message, $conversation);

        return $message;
    }

    private function findConversationForUser(int $conversationId, User $user): Conversation
    {
        return Conversation::query()
            ->where('id', $conversationId)
            ->where(fn (Builder $q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))
            ->firstOrFail();
    }

  /**
   * @return array<string, mixed>
   */
    private function formatConversation(Conversation $conversation, User $user): array
    {
        $unread = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();

        $other = $conversation->sender_id === $user->id ? $conversation->receiver : $conversation->sender;

        return [
            'id' => $conversation->id,
            'subject' => $conversation->subject,
            'product_id' => $conversation->product_id,
            'other_user' => [
                'id' => $other?->id,
                'name' => $other?->name,
                'slug' => $other?->slug,
            ],
            'num_unread_messages' => $unread,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }

  /**
   * @return array<string, mixed>
   */
    private function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'message' => $message->message,
            'is_read' => $message->is_read,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
