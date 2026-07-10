<?php

use App\Models\User;
use App\Modules\Selloff\Location\Models\Country;
use App\Modules\Selloff\Messaging\Models\Conversation;
use App\Modules\Selloff\Messaging\Models\Message;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('admin can update and delete country', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $country = Country::query()->where('code', 'NG')->firstOrFail();

    $this->putJson('/api/v1/admin/locations/countries/'.$country->id, [
        'name' => 'Nigeria Updated',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nigeria Updated');

    $this->deleteJson('/api/v1/admin/locations/countries/'.$country->id, [], adminPinHeaders())
        ->assertOk();

    $this->assertDatabaseMissing('countries', ['id' => $country->id]);
});

test('admin can update city', function () {
    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $city = \App\Modules\Selloff\Location\Models\City::query()->firstOrFail();

    $this->putJson('/api/v1/admin/locations/cities/'.$city->id, [
        'name' => 'Updated City',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated City');
});

test('buyer can list send and read messages', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($buyer);

    $this->postJson('/api/v1/messages/send-new-conversation-message', [
        'receiver_id' => $vendor->id,
        'subject' => 'Product question',
        'message' => 'Is this still available?',
    ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $conversation = Conversation::query()
        ->where('sender_id', $buyer->id)
        ->where('receiver_id', $vendor->id)
        ->firstOrFail();

    $this->getJson('/api/v1/messages/latest-conversations')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['id' => $conversation->id]);

    $this->getJson('/api/v1/messages/'.$conversation->id)
        ->assertOk()
        ->assertJsonFragment(['message' => 'Is this still available?']);

    Sanctum::actingAs($vendor);

    Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $vendor->id,
        'receiver_id' => $buyer->id,
        'message' => 'Yes, it is.',
        'is_read' => false,
    ]);

    Sanctum::actingAs($buyer);

    $this->getJson('/api/v1/messages/unread-conversations-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);

    $this->getJson('/api/v1/messages/set-conversation-messages-as-read/'.$conversation->id)
        ->assertOk();

    $this->getJson('/api/v1/messages/unread-conversations-count')
        ->assertOk()
        ->assertJsonPath('data.count', 0);

    $this->postJson('/api/v1/messages/send-conversation-message', [
        'conversation_id' => $conversation->id,
        'message' => 'Great, thanks!',
    ])
        ->assertCreated()
        ->assertJsonPath('data.message', 'Great, thanks!');
});
