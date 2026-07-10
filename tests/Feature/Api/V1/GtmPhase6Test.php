<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('send new conversation message returns chat messages gtm event', function () {
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
    expect($events)->toBeArray();
    expect($events[0]['event'])->toBe('chat_messages');
    expect($events[0]['eventData']['sender'])->toBe('Buyer');
    expect($events[0]['eventData']['item_id'])->toBe((string) $product->id);
    expect($events[0]['eventData']['buyer_id'])->toBe((string) $buyer->id);
    expect($events[0]['eventData']['seller_id'])->toBe((string) $vendor->id);
});

test('send conversation reply returns chat messages gtm event', function () {
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
    expect($events[0]['event'])->toBe('chat_messages');
    expect($events[0]['eventData']['sender'])->toBe('Seller');
});
