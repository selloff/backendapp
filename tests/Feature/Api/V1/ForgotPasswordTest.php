<?php

use App\Models\User;
use App\Modules\Auth\Actions\SendPasswordResetLinkAction;
use App\Modules\Selloff\Notification\Mail\TransactionalMail;
use App\Services\Auth\PasswordResetEmailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    config(['selloff.security.turnstile_disabled' => true]);
    config(['selloff.spa_url' => 'https://staging.selloff.ng']);
});

test('forgot password sends spa reset link', function () {
    Mail::fake();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'buyer@selloff.test',
    ])
        ->assertOk()
        ->assertJsonPath('success', true);

    Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail): bool {
        $url = (string) ($mail->templateData['url'] ?? '');

        return $mail->hasTo('buyer@selloff.test')
            && $mail->template === 'main'
            && $mail->mailSubject === 'Reset your Selloff password'
            && str_contains($url, 'https://staging.selloff.ng/reset-password?')
            && str_contains($url, 'email=buyer%40selloff.test');
    });
});

test('forgot password returns service unavailable when mail transport fails', function () {
    $mock = Mockery::mock(PasswordResetEmailService::class);
    $mock->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('SMTP connection failed'));
    app()->instance(PasswordResetEmailService::class, $mock);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'buyer@selloff.test',
    ])
        ->assertStatus(503)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'We could not send the reset email. Check API mail settings and try again later.');
});

test('send password reset link action maps transport failures to status', function () {
    $mock = Mockery::mock(PasswordResetEmailService::class);
    $mock->shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('SMTP connection failed'));
    app()->instance(PasswordResetEmailService::class, $mock);

    $status = app(SendPasswordResetLinkAction::class)->execute('buyer@selloff.test');

    expect($status)->toBe(SendPasswordResetLinkAction::STATUS_MAIL_FAILED);
});

afterEach(function () {
    Mockery::close();
});
