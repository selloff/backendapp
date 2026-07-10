<?php

use App\Models\User;
use App\Modules\Selloff\Notification\Mail\TestMail;
use App\Modules\Selloff\Notification\Mail\TransactionalMail;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Services\TransactionalEmailService;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Models\PlatformSetting;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    EmailJob::query()->delete();
    app(PlatformSettingsService::class)->flushCache();
});

test('selloff send email jobs processes pending body jobs and marks them sent', function () {
    Mail::fake();

    $job = EmailJob::query()->create([
        'to_email' => 'buyer@selloff.test',
        'email_type' => TransactionalEmailType::ORDER_CONFIRMATION,
        'subject' => 'Order #1001 confirmation',
        'body' => 'Your order total is 5000 NGN.',
        'status' => 'pending',
        'metadata' => ['type' => TransactionalEmailType::ORDER_CONFIRMATION],
    ]);

    $this->artisan('selloff:send-email-jobs')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe('sent')
        ->and($job->sent_at)->not->toBeNull()
        ->and($job->attempts)->toBe(1);
});

test('selloff send email jobs sends templated jobs through transactional mail', function () {
    Mail::fake();

    $job = EmailJob::query()->create([
        'to_email' => 'buyer@selloff.test',
        'email_type' => TransactionalEmailType::ACTIVATION,
        'subject' => 'Confirm your email address',
        'template' => 'main',
        'template_data' => [
            'title' => 'Confirm your email',
            'content' => 'Please confirm your email address.',
            'url' => 'https://app.selloff.test/confirm-email?token=abc',
            'buttonText' => 'Confirm email',
        ],
        'status' => 'pending',
        'metadata' => ['type' => TransactionalEmailType::ACTIVATION],
    ]);

    $this->artisan('selloff:send-email-jobs')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe('sent');

    Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail): bool {
        return $mail->hasTo('buyer@selloff.test')
            && $mail->mailSubject === 'Confirm your email address'
            && $mail->template === 'main';
    });
});

test('selloff send email jobs skips jobs when admin toggle is disabled', function () {
    Mail::fake();

    PlatformSetting::query()->updateOrCreate(
        ['key' => 'email_verification'],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();

    $job = EmailJob::query()->create([
        'to_email' => 'buyer@selloff.test',
        'email_type' => TransactionalEmailType::ACTIVATION,
        'subject' => 'Confirm your email address',
        'body' => 'Please confirm your email.',
        'status' => 'pending',
        'metadata' => ['type' => TransactionalEmailType::ACTIVATION],
    ]);

    $this->artisan('selloff:send-email-jobs')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe('skipped')
        ->and($job->skipped_at)->not->toBeNull();

    Mail::assertNothingSent();
});

test('selloff send email jobs defers scheduled jobs until due', function () {
    Mail::fake();

    $job = EmailJob::query()->create([
        'to_email' => 'buyer@selloff.test',
        'email_type' => TransactionalEmailType::ORDER_CONFIRMATION,
        'subject' => 'Delayed order confirmation',
        'body' => 'Delayed body',
        'status' => 'pending',
        'scheduled_at' => now()->addMinutes(10),
        'metadata' => ['type' => TransactionalEmailType::ORDER_CONFIRMATION],
    ]);

    $this->artisan('selloff:send-email-jobs')->assertSuccessful();

    $job->refresh();

    expect($job->status)->toBe('pending')
        ->and($job->sent_at)->toBeNull();

    Mail::assertNothingSent();
});

test('transactional email service does not queue when toggle is disabled', function () {
    EmailJob::query()->delete();

    PlatformSetting::query()->updateOrCreate(
        ['key' => 'email_option_new_order'],
        ['value' => false, 'group' => 'email'],
    );
    app(PlatformSettingsService::class)->flushCache();

    $service = app(TransactionalEmailService::class);

    $job = $service->queue(
        TransactionalEmailType::ORDER_CONFIRMATION,
        'buyer@selloff.test',
        ['body' => 'Should not queue'],
        subject: 'Order confirmation',
    );

    expect($job)->toBeNull()
        ->and(EmailJob::query()->count())->toBe(0);
});

test('admin test email uses platform mail service with branded template', function () {
    Mail::fake();

    $admin = User::query()->where('email', 'superadmin@selloff.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/email/test', [
        'email' => 'ops@selloff.test',
    ], superAdminPinHeaders())
        ->assertOk()
        ->assertJsonPath('data.sent', true);

    Mail::assertSent(TestMail::class, function (TestMail $mail): bool {
        return $mail->hasTo('ops@selloff.test')
            && $mail->mailSubject === 'Selloff test email';
    });
});
