<?php

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use App\Modules\Selloff\Notification\Models\UserNotificationRead;
use App\Modules\Selloff\Order\Models\QuoteRequest;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('user notifications require authentication', function () {
    $this->getJson('/api/v1/notifications/inbox')->assertUnauthorized();
});

test('vendor notifications include product moderation activity', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $product = Product::query()
        ->where('vendor_id', $vendor->id)
        ->where('is_verified', true)
        ->where('is_deleted', false)
        ->where('is_draft', false)
        ->orderByDesc('updated_at')
        ->firstOrFail();
    $product->updated_at = $product->created_at->copy()->addHour();
    $product->save();

    Sanctum::actingAs($vendor);

    $response = $this->getJson('/api/v1/notifications/inbox')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => [
                'unread_count',
                'groups' => [
                    '*' => [
                        'type',
                        'label',
                        'list_url',
                        'unread_count',
                        'total_count',
                        'items' => [
                            '*' => [
                                'key',
                                'title',
                                'body',
                                'created_at',
                                'is_read',
                                'action_url',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

    $approvedGroup = collect($response->json('data.groups'))
        ->firstWhere('type', 'product_approved');

    expect($approvedGroup)->not->toBeNull();
    expect(collect($approvedGroup['items'])->pluck('key'))->toContain('product_approved:'.$product->id);
    expect($response->json('data.unread_count'))->toBeGreaterThanOrEqual(1);
});

test('member notifications include unread message conversations', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    $conversation = Conversation::query()->create([
        'sender_id' => $vendor->id,
        'receiver_id' => $buyer->id,
        'subject' => 'Notification test',
        'last_message_at' => now(),
    ]);

    Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $vendor->id,
        'receiver_id' => $buyer->id,
        'message' => 'Hello buyer',
        'is_read' => false,
    ]);

    Sanctum::actingAs($buyer);

    $response = $this->getJson('/api/v1/notifications/inbox')->assertOk();
    $messageGroup = collect($response->json('data.groups'))->firstWhere('type', 'new_message');

    expect($messageGroup)->not->toBeNull();
    expect(collect($messageGroup['items'])->pluck('key'))->toContain('new_message:'.$conversation->id);
});

test('mark read is scoped per user', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $quote = QuoteRequest::query()
        ->where('seller_id', $vendor->id)
        ->where('status', 'pending')
        ->firstOrFail();

    $key = 'quote_request:'.$quote->id;

    Sanctum::actingAs($vendor);
    $before = $this->getJson('/api/v1/notifications/inbox')->json('data.unread_count');

    $this->postJson('/api/v1/notifications/'.rawurlencode($key).'/read')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('user_notification_reads', [
        'user_id' => $vendor->id,
        'notification_key' => $key,
    ]);

    $after = $this->getJson('/api/v1/notifications/inbox')->json('data.unread_count');
    expect($after)->toBe($before - 1);

    Sanctum::actingAs($buyer);
    expect(UserNotificationRead::query()->where('user_id', $buyer->id)->where('notification_key', $key)->exists())->toBeFalse();
});

test('vendor quote requests appear in vendor inbox', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $quote = QuoteRequest::query()
        ->where('seller_id', $vendor->id)
        ->where('status', 'pending')
        ->firstOrFail();

    Sanctum::actingAs($vendor);

    $group = collect($this->getJson('/api/v1/notifications/inbox')->json('data.groups'))
        ->firstWhere('type', 'quote_request');

    expect($group)->not->toBeNull();
    expect(collect($group['items'])->pluck('key'))->toContain('quote_request:'.$quote->id);
});

test('unread count endpoint returns count', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['count']]);
});

test('mark all read clears unread notifications for user', function () {
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($vendor);

    expect($this->getJson('/api/v1/notifications/inbox')->json('data.unread_count'))->toBeGreaterThan(0);

    $this->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($this->getJson('/api/v1/notifications/inbox')->json('data.unread_count'))->toBe(0);
});
