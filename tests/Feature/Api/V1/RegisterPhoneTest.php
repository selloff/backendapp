<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use App\Services\Platform\PlatformSettingsService;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    config(['selloff.security.turnstile_disabled' => true]);
    app(PlatformSettingsService::class)->upsertMany(['turnstile_status' => false]);
});

test('registration requires a unique phone number', function () {
    $existing = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $this->postJson('/api/v1/auth/register', registerPayload([
        'phone_number' => $existing->phone_number,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);
});

test('registration rejects missing phone number', function () {
    $payload = registerPayload();
    unset($payload['phone_number']);

    $this->postJson('/api/v1/auth/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);
});

test('profile update rejects duplicate phone number', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();

    Sanctum::actingAs($buyer);

    $this->patchJson('/api/v1/auth/me', [
        'phone_number' => $vendor->phone_number,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);
});

test('profile update allows keeping own phone number', function () {
    $buyer = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    $phone = $buyer->phone_number ?? '+2348099900001';
    $buyer->update(['phone_number' => $phone]);

    Sanctum::actingAs($buyer);

    $this->patchJson('/api/v1/auth/me', [
        'phone_number' => $phone,
    ])
        ->assertOk()
        ->assertJsonPath('data.user.phone_number', $phone);
});

test('mobile registration requires unique phone number', function () {
    $existing = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    $this->postJson('/api/v1/mobile/register', registerPayload([
        'email' => 'mobile.duplicate.phone@selloff.test',
        'password' => 'password123',
        'phone_number' => $existing->phone_number,
        'fullname' => 'Mobile Duplicate',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);
});
