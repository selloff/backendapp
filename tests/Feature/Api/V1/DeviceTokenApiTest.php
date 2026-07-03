<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Tests\TestCase;

class DeviceTokenApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    }

    public function test_authenticated_user_can_register_device_token(): void
    {
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
    }

    public function test_registering_same_token_updates_owner(): void
    {
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
    }

    public function test_authenticated_user_can_delete_own_device_token(): void
    {
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
    }

    public function test_guest_cannot_register_device_token(): void
    {
        $response = $this->postJson('/api/v1/notifications/device-tokens', [
            'token' => 'guest-token',
            'platform' => 'android',
        ]);

        $response->assertUnauthorized();
    }
}
