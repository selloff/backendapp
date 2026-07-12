<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Modules\Selloff\Notification\Mail\TransactionalMail;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Services\Auth\EmailVerificationService;
use App\Services\Platform\PlatformSettingsService;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    config(['selloff.security.turnstile_disabled' => true]);
    config(['selloff.spa_url' => 'http://localhost:5173']);
    app(PlatformSettingsService::class)->flushCache();
    EmailJob::query()->delete();
});

function disableEmailOption(string $key): void
{
    PlatformSetting::query()->updateOrCreate(
        ['key' => $key],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();
}

test('registration queues a branded activation email job', function () {
    $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'New',
        'last_name' => 'Member',
        'email' => 'new.member@selloff.test',
        'password' => 'secret',
        'password_confirmation' => 'secret',
    ]))->assertCreated();

    $job = EmailJob::query()->where('email_type', 'activation')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('new.member@selloff.test')
        ->and($job->template)->toBe('main')
        ->and($job->status)->toBe('pending');

    expect((string) ($job->template_data['url'] ?? ''))->toContain('http://localhost:5173/confirm-email?token=');
});

test('registration does not queue activation email when email verification is disabled', function () {
    disableEmailOption('email_verification');

    $this->postJson('/api/v1/auth/register', registerPayload([
        'first_name' => 'No',
        'last_name' => 'Verify',
        'email' => 'no.verify@selloff.test',
        'password' => 'secret',
        'password_confirmation' => 'secret',
    ]))->assertCreated();

    expect(EmailJob::query()->where('email_type', 'activation')->count())->toBe(0);
});

test('verifying email queues a welcome email for email password users', function () {
    $user = User::factory()->create([
        'email' => 'verify.welcome@selloff.test',
        'email_verified_at' => null,
    ]);

    $token = app(EmailVerificationService::class)->issueToken($user);

    $this->postJson('/api/v1/auth/verify-email/'.$token->token)->assertOk();

    $job = EmailJob::query()->where('email_type', 'welcome')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('verify.welcome@selloff.test')
        ->and($job->template)->toBe('welcome');
});

test('verifying email does not queue welcome when welcome emails are disabled', function () {
    disableEmailOption('email_option_welcome');

    $user = User::factory()->create([
        'email' => 'no.welcome@selloff.test',
        'email_verified_at' => null,
    ]);

    $token = app(EmailVerificationService::class)->issueToken($user);

    $this->postJson('/api/v1/auth/verify-email/'.$token->token)->assertOk();

    expect(EmailJob::query()->where('email_type', 'welcome')->count())->toBe(0);
});

test('activation job renders through the transactional mailer', function () {
    Mail::fake();

    EmailJob::query()->create([
        'to_email' => 'render.me@selloff.test',
        'email_type' => 'activation',
        'subject' => 'Confirm your account',
        'template' => 'main',
        'template_data' => [
            'title' => 'Confirm your account',
            'content' => 'Please confirm your email.',
            'url' => 'http://localhost:5173/confirm-email?token=abc',
            'buttonText' => 'Confirm your account',
        ],
        'status' => 'pending',
        'metadata' => ['type' => 'activation'],
    ]);

    $this->artisan('selloff:send-email-jobs')->assertSuccessful();

    Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail): bool {
        return $mail->hasTo('render.me@selloff.test')
            && $mail->template === 'main'
            && $mail->mailSubject === 'Confirm your account';
    });
});

test('google oauth first signup queues a welcome email immediately', function () {
    config([
        'services.google.client_id' => 'test-google-client',
        'services.google.client_secret' => 'test-google-secret',
        'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-welcome-1',
        'name' => 'Google Welcome',
        'email' => 'google.welcome@selloff.test',
    ]));

    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/api/v1/auth/oauth/google/callback')
        ->assertRedirect();

    $job = EmailJob::query()->where('email_type', 'welcome')->first();

    expect($job)->not->toBeNull()
        ->and($job->to_email)->toBe('google.welcome@selloff.test');
});

test('returning oauth users do not receive a duplicate welcome email', function () {
    config([
        'services.google.client_id' => 'test-google-client',
        'services.google.client_secret' => 'test-google-secret',
        'services.google.redirect' => 'http://localhost/api/v1/auth/oauth/google/callback',
    ]);

    $existing = User::query()->where('email', 'buyer@selloff.test')->firstOrFail();

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-existing-1',
        'name' => $existing->first_name.' '.$existing->last_name,
        'email' => $existing->email,
    ]));

    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/api/v1/auth/oauth/google/callback')
        ->assertRedirect();

    expect(EmailJob::query()->where('email_type', 'welcome')->count())->toBe(0);
});

test('forgot password sends branded reset email immediately', function () {
    Mail::fake();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'buyer@selloff.test',
    ])->assertOk()->assertJsonPath('success', true);

    Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail): bool {
        $url = (string) ($mail->templateData['url'] ?? '');

        return $mail->hasTo('buyer@selloff.test')
            && $mail->template === 'main'
            && $mail->mailSubject === 'Reset your Selloff password'
            && str_contains($url, '/reset-password?');
    });
});

test('forgot password skips sending when reset password emails are disabled', function () {
    disableEmailOption('email_option_reset_password');

    Mail::fake();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'buyer@selloff.test',
    ])->assertOk()->assertJsonPath('success', true);

    Mail::assertNothingSent();
});
