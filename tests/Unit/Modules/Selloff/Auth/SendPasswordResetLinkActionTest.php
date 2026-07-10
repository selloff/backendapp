<?php

use App\Modules\Auth\Actions\SendPasswordResetLinkAction;
use App\Modules\Selloff\Notification\Mail\TransactionalMail;
use App\Services\Auth\PasswordResetEmailService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

describe('SendPasswordResetLinkAction', function () {
    beforeEach(function () {
        $this->artisan('selloff:migrate', ['--fresh' => true, '--seed' => true]);
    });

    test('returns password broker status on success', function () {
        Mail::fake();

        $status = app(SendPasswordResetLinkAction::class)->execute('buyer@selloff.test');

        expect($status)->toBe(Password::RESET_LINK_SENT);

        Mail::assertSent(TransactionalMail::class, function (TransactionalMail $mail): bool {
            return $mail->hasTo('buyer@selloff.test')
                && $mail->template === 'main';
        });
    });

    test('maps transport failures to mail failed status', function () {
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
});
