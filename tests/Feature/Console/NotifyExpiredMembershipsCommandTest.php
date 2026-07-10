<?php

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipExpiryNotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    config(['selloff.spa_url' => 'https://app.selloff.test']);
});

test('command sends expiry email with renew link', function () {
    Mail::fake();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subHours(6),
            'is_active' => true,
            'expiry_notified_at' => null,
        ],
    );

    Artisan::call('selloff:notify-expired-memberships');

    $this->assertStringContainsString('Sent 1 membership expiry notification(s).', Artisan::output());

    expect(UserMembershipPlan::query()
        ->where('user_id', $vendor->id)
        ->where('membership_plan_id', $plan->id)
        ->value('expiry_notified_at'))->not->toBeNull();
});

test('service notify includes renew link in email body', function () {
    $capturedBody = null;

    Mail::shouldReceive('raw')
        ->once()
        ->withArgs(function (string $body) use (&$capturedBody) {
            $capturedBody = $body;

            return str_contains($body, 'https://app.selloff.test/vendor/membership/subscribe')
                && str_contains($body, 'Demo Vendor Pro');
        });

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    $subscription = UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subHours(6),
            'is_active' => true,
            'expiry_notified_at' => null,
        ],
    );

    app(MembershipExpiryNotificationService::class)->notify($subscription->fresh());

    expect($capturedBody)->not->toBeNull();
});

test('command skips already notified subscriptions', function () {
    Mail::fake();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subDay(),
            'is_active' => true,
            'expiry_notified_at' => now()->subHour(),
        ],
    );

    Artisan::call('selloff:notify-expired-memberships');

    Mail::assertNothingSent();
    $this->assertStringContainsString('Sent 0 membership expiry notification(s).', Artisan::output());
});

test('command skips active unexpired subscriptions', function () {
    Mail::fake();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'expiry_notified_at' => null,
        ],
    );

    Artisan::call('selloff:notify-expired-memberships');

    Mail::assertNothingSent();
});

test('service renew url uses spa url', function () {
    $service = app(MembershipExpiryNotificationService::class);

    expect($service->renewUrl())->toBe('https://app.selloff.test/vendor/membership/subscribe');
});

test('second run is idempotent for same expiry', function () {
    Mail::fake();

    $vendor = User::query()->where('email', 'vendor@selloff.test')->firstOrFail();
    $plan = MembershipPlan::query()->where('title', 'Demo Vendor Pro')->firstOrFail();

    UserMembershipPlan::query()->updateOrCreate(
        ['user_id' => $vendor->id, 'membership_plan_id' => $plan->id],
        [
            'starts_at' => now()->subMonths(2),
            'expires_at' => now()->subHours(2),
            'is_active' => true,
            'expiry_notified_at' => null,
        ],
    );

    Artisan::call('selloff:notify-expired-memberships');
    $this->assertStringContainsString('Sent 1 membership expiry notification(s).', Artisan::output());

    Artisan::call('selloff:notify-expired-memberships');
    $this->assertStringContainsString('Sent 0 membership expiry notification(s).', Artisan::output());
});
