<?php

use App\Models\User;
use App\Modules\Selloff\User\Services\UserPresenceService;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    Cache::flush();
});

test('authenticated user can record presence heartbeat', function () {
    $user = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/presence')
        ->assertOk()
        ->assertJsonPath('success', true);

    $user->refresh();

    expect($user->last_seen_at)->not->toBeNull()
        ->and($user->last_seen_at->greaterThan(now()->subMinute()))->toBeTrue();
});

test('presence heartbeat is cache gated to one database write per minute', function () {
    $user = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/presence')->assertOk();
    $user->refresh();
    $firstSeen = $user->last_seen_at?->copy();

    $this->travel(10)->seconds();
    $this->postJson('/api/v1/auth/presence')->assertOk();
    $user->refresh();

    expect($firstSeen)->not->toBeNull()
        ->and($user->last_seen_at?->equalTo($firstSeen))->toBeTrue();
});

test('login records initial presence', function () {
    config(['selloff.security.turnstile_disabled' => true]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'buyer@selloff.test',
        'password' => 'password',
    ])->assertOk();

    $user = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    expect($user->last_seen_at)->not->toBeNull()
        ->and($user->last_seen_at->greaterThan(now()->subMinute()))->toBeTrue();
});

test('presence endpoint requires authentication', function () {
    $this->postJson('/api/v1/auth/presence')->assertUnauthorized();
});

test('presence service marks seeded user online immediately after heartbeat', function () {
    $user = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/auth/presence')->assertOk();

    expect(app(UserPresenceService::class)->isOnline($user->fresh()))->toBeTrue();
});
