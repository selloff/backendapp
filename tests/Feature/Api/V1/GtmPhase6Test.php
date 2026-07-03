<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GtmPhase6Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_send_new_conversation_message_returns_chat_messages_gtm_event(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/v1/messages/send-new-conversation-message', [
            'receiver_id' => $vendor->id,
            'message' => 'Is this still available?',
            'subject' => 'Question about listing',
            'product_id' => $product->id,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $events = $response->json('data.gtm_events');
        $this->assertIsArray($events);
        $this->assertSame('chat_messages', $events[0]['event']);
        $this->assertSame('Buyer', $events[0]['eventData']['sender']);
        $this->assertSame((string) $product->id, $events[0]['eventData']['item_id']);
        $this->assertSame((string) $buyer->id, $events[0]['eventData']['buyer_id']);
        $this->assertSame((string) $vendor->id, $events[0]['eventData']['seller_id']);
    }

    public function test_send_conversation_reply_returns_chat_messages_gtm_event(): void
    {
        $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
        $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
        $product = Product::query()->where('sku', 'DEMO-CLASSIFIED-1')->firstOrFail();

        Sanctum::actingAs($buyer);

        $conversationId = $this->postJson('/api/v1/messages/send-new-conversation-message', [
            'receiver_id' => $vendor->id,
            'message' => 'First message',
            'subject' => 'Follow up',
            'product_id' => $product->id,
        ])->assertCreated()->json('data.conversation_id');

        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/v1/messages/send-conversation-message', [
            'conversation_id' => $conversationId,
            'message' => 'Yes, still available.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $events = $response->json('data.gtm_events');
        $this->assertSame('chat_messages', $events[0]['event']);
        $this->assertSame('Seller', $events[0]['eventData']['sender']);
    }
}
