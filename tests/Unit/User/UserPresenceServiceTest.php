<?php

use App\Models\User;
use App\Modules\Selloff\User\Services\UserPresenceService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true]);
    Cache::flush();
});

test('record activity updates last seen at once per throttle window', function () {
    $user = User::factory()->create([
        'last_seen_at' => now()->subHour(),
    ]);

    $service = app(UserPresenceService::class);

    $service->recordActivity($user);
    $user->refresh();

    expect($user->last_seen_at)->not->toBeNull()
        ->and($user->last_seen_at->greaterThan(now()->subMinute()))->toBeTrue();

    $firstSeen = $user->last_seen_at->copy();

    $this->travel(10)->seconds();
    $service->recordActivity($user);
    $user->refresh();

    expect($user->last_seen_at->equalTo($firstSeen))->toBeTrue();
});

test('record activity writes again after throttle window expires', function () {
    $user = User::factory()->create([
        'last_seen_at' => now()->subHour(),
    ]);

    $service = app(UserPresenceService::class);
    $service->recordActivity($user);
    $user->refresh();

    $firstSeen = $user->last_seen_at->copy();

    $this->travel(61)->seconds();
    $service->recordActivity($user);
    $user->refresh();

    expect($user->last_seen_at->greaterThan($firstSeen))->toBeTrue();
});

test('is online when presence cache is warm', function () {
    $user = User::factory()->create([
        'last_seen_at' => now()->subHour(),
    ]);

    Cache::put("user_presence:{$user->id}", now()->timestamp, 60);

    expect(app(UserPresenceService::class)->isOnline($user))->toBeTrue();
});

test('is online when last seen is within the configured window', function () {
    $user = User::factory()->create([
        'last_seen_at' => now()->subSeconds(90),
    ]);

    expect(app(UserPresenceService::class)->isOnline($user))->toBeTrue();
});

test('is offline when last seen is outside the configured window', function () {
    $user = User::factory()->create([
        'last_seen_at' => now()->subMinutes(5),
    ]);

    expect(app(UserPresenceService::class)->isOnline($user))->toBeFalse();
});

test('is offline when last seen is missing', function () {
    $user = User::factory()->create([
        'last_seen_at' => null,
    ]);

    expect(app(UserPresenceService::class)->isOnline($user))->toBeFalse();
});
