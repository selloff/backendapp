<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Auth\Actions\SendPasswordResetLinkAction;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
        config(['selloff.security.turnstile_disabled' => true]);
        config(['selloff.spa_url' => 'https://staging.selloff.ng']);
    }

    public function test_forgot_password_sends_spa_reset_link(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'buyer@selloff.test',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Notification::assertSentTo(
            User::query()->where('email', 'buyer@selloff.test')->firstOrFail(),
            ResetPassword::class,
            function (ResetPassword $notification): bool {
                $message = $notification->toMail(User::query()->where('email', 'buyer@selloff.test')->firstOrFail());

                return $message instanceof MailMessage
                    && str_contains((string) $message->actionUrl, 'https://staging.selloff.ng/reset-password?')
                    && str_contains((string) $message->actionUrl, 'email=buyer%40selloff.test');
            },
        );
    }

    public function test_forgot_password_returns_service_unavailable_when_mail_transport_fails(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new \RuntimeException('SMTP connection failed'));

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'buyer@selloff.test',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'We could not send the reset email. Check API mail settings and try again later.');
    }

    public function test_send_password_reset_link_action_maps_transport_failures_to_status(): void
    {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new \RuntimeException('SMTP connection failed'));

        $status = app(SendPasswordResetLinkAction::class)->execute('buyer@selloff.test');

        $this->assertSame(SendPasswordResetLinkAction::STATUS_MAIL_FAILED, $status);
    }
}
