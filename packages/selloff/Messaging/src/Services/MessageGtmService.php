<?php

namespace App\Modules\Selloff\Messaging\Services;

use App\Modules\Selloff\Messaging\Models\Message;
use App\Support\Gtm\GtmEventFactory;

class MessageGtmService
{
    public function __construct(
        private readonly GtmEventFactory $factory,
    ) {}

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function eventsForMessage(Message $message): array
    {
        $message->loadMissing([
            'conversation.product.translations',
            'conversation.product.vendor',
            'conversation.sender',
            'conversation.receiver',
            'sender',
            'receiver',
        ]);

        $conversation = $message->conversation;
        if ($conversation === null) {
            return [];
        }

        $product = $conversation->product;
        $seller = $product?->vendor;
        if ($seller === null) {
            return [];
        }

        $buyer = $message->sender_id === $seller->id
            ? $message->receiver
            : $message->sender;

        if ($buyer === null) {
            return [];
        }

        $translation = $product?->translations->firstWhere('locale', 'en')
            ?? $product?->translations->first();

        $senderRole = $message->sender_id === $seller->id ? 'Seller' : 'Buyer';

        return $this->factory->list('chat_messages', [
            'chat_id' => $conversation->id,
            'message_id' => $message->id,
            'sender' => $senderRole,
            'item_id' => (string) ($product?->id ?? ''),
            'item_title' => (string) ($translation?->title ?? ''),
            'subject' => (string) ($conversation->subject ?? ''),
            'message' => (string) $message->message,
            'buyer_id' => (string) $buyer->id,
            'buyer_name' => trim($buyer->first_name.' '.$buyer->last_name),
            'buyer_phone' => (string) ($buyer->phone_number ?? ''),
            'buyer_email' => (string) ($buyer->email ?? ''),
            'seller_id' => (string) $seller->id,
            'seller_name' => trim($seller->first_name.' '.$seller->last_name),
            'seller_username' => (string) ($seller->username ?? $seller->slug ?? ''),
            'seller_phone' => (string) ($seller->phone_number ?? ''),
            'seller_email' => (string) ($seller->email ?? ''),
        ]);
    }
}
