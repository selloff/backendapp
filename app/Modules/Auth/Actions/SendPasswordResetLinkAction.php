<?php

namespace App\Modules\Auth\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class SendPasswordResetLinkAction
{
    public const STATUS_MAIL_FAILED = 'mail_failed';

    public function execute(string $email): string
    {
        try {
            return Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $exception) {
            Log::error('Password reset email failed to send.', [
                'email' => $email,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return self::STATUS_MAIL_FAILED;
        }
    }
}
