<?php

use App\Models\User;
use App\Modules\Auth\Actions\SendPasswordResetLinkAction;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    config(['selloff.security.turnstile_disabled' => true]);
    config(['selloff.spa_url' => 'https://staging.selloff.ng']);
});

test('forgot password sends spa reset link', function () {
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
});

test('forgot password returns service unavailable when mail transport fails', function () {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andThrow(new \RuntimeException('SMTP connection failed'));

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'buyer@selloff.test',
    ])
        ->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'We could not send the reset email. Check API mail settings and try again later.');
});

test('send password reset link action maps transport failures to status', function () {
    Password::shouldReceive('sendResetLink')
        ->once()
        ->andThrow(new \RuntimeException('SMTP connection failed'));

    $status = app(SendPasswordResetLinkAction::class)->execute('buyer@selloff.test');

    expect($status)->toBe(SendPasswordResetLinkAction::STATUS_MAIL_FAILED);
});
