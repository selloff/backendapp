<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Modules\Selloff\Payment\Models\MembershipPlan;
use App\Modules\Selloff\Payment\Models\UserMembershipPlan;
use App\Modules\Selloff\Payment\Services\MembershipExpiryNotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyExpiredMembershipsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
        config(['selloff.spa_url' => 'https://app.selloff.test']);
    }

    public function test_command_sends_expiry_email_with_renew_link(): void
    {
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

        $this->assertNotNull(
            UserMembershipPlan::query()
                ->where('user_id', $vendor->id)
                ->where('membership_plan_id', $plan->id)
                ->value('expiry_notified_at'),
        );
    }

    public function test_service_notify_includes_renew_link_in_email_body(): void
    {
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

        $this->assertNotNull($capturedBody);
    }

    public function test_command_skips_already_notified_subscriptions(): void
    {
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
    }

    public function test_command_skips_active_unexpired_subscriptions(): void
    {
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
    }

    public function test_service_renew_url_uses_spa_url(): void
    {
        $service = app(MembershipExpiryNotificationService::class);

        $this->assertSame(
            'https://app.selloff.test/vendor/membership/subscribe',
            $service->renewUrl(),
        );
    }

    public function test_second_run_is_idempotent_for_same_expiry(): void
    {
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
    }
}
