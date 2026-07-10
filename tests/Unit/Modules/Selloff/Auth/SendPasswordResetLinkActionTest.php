<?php

use App\Modules\Auth\Actions\SendPasswordResetLinkAction;
use Illuminate\Support\Facades\Password;

describe('SendPasswordResetLinkAction', function () {
    test('returns password broker status on success', function () {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'buyer@selloff.test'])
            ->andReturn(Password::RESET_LINK_SENT);

        $status = app(SendPasswordResetLinkAction::class)->execute('buyer@selloff.test');

        expect($status)->toBe(Password::RESET_LINK_SENT);
    });

    test('maps transport failures to mail failed status', function () {
        Password::shouldReceive('sendResetLink')
            ->once()
            ->andThrow(new RuntimeException('SMTP connection failed'));

        $status = app(SendPasswordResetLinkAction::class)->execute('buyer@selloff.test');

        expect($status)->toBe(SendPasswordResetLinkAction::STATUS_MAIL_FAILED);
    });
});
