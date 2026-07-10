<?php

use App\Models\User;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
});

test('authenticated user can register device token', function () {
    $user = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $response = $this->actingAs($user)->postJson('/api/v1/notifications/device-tokens', [
        'token' => 'fcm-test-token-abc',
        'platform' => 'android',
        'device_id' => 'pixel-8',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.platform', 'android');

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $user->id,
        'token' => 'fcm-test-token-abc',
        'platform' => 'android',
        'device_id' => 'pixel-8',
    ]);
});

test('registering same token updates owner', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::factory()->create();

    $this->actingAs($buyer)->postJson('/api/v1/notifications/device-tokens', [
        'token' => 'shared-device-token',
        'platform' => 'ios',
    ])->assertCreated();

    $this->actingAs($vendor)->postJson('/api/v1/notifications/device-tokens', [
        'token' => 'shared-device-token',
        'platform' => 'ios',
    ])->assertCreated();

    $this->assertDatabaseHas('device_tokens', [
        'user_id' => $vendor->id,
        'token' => 'shared-device-token',
    ]);

    $this->assertDatabaseMissing('device_tokens', [
        'user_id' => $buyer->id,
        'token' => 'shared-device-token',
    ]);
});

test('authenticated user can delete own device token', function () {
    $user = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $this->actingAs($user)->postJson('/api/v1/notifications/device-tokens', [
        'token' => 'token-to-delete',
        'platform' => 'android',
    ])->assertCreated();

    $encoded = rawurlencode('token-to-delete');
    $response = $this->actingAs($user)->deleteJson("/api/v1/notifications/device-tokens/{$encoded}");

    $response->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseMissing('device_tokens', [
        'user_id' => $user->id,
        'token' => 'token-to-delete',
    ]);
});

test('guest cannot register device token', function () {
    $response = $this->postJson('/api/v1/notifications/device-tokens', [
        'token' => 'guest-token',
        'platform' => 'android',
    ]);

    $response->assertUnauthorized();
});
