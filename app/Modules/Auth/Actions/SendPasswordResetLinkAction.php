<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Selloff\Notification\Services\EmailOptionGate;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use App\Services\Auth\PasswordResetEmailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class SendPasswordResetLinkAction
{
    public const STATUS_MAIL_FAILED = 'mail_failed';

    public function __construct(
        private readonly EmailOptionGate $gate,
        private readonly PasswordResetEmailService $passwordResetEmail,
    ) {}

    public function execute(string $email): string
    {
        if (! $this->gate->isEnabled(TransactionalEmailType::RESET_PASSWORD)) {
            return Password::RESET_LINK_SENT;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return Password::RESET_LINK_SENT;
        }

        try {
            $token = Password::broker()->createToken($user);
            $this->passwordResetEmail->send($user, $token);

            return Password::RESET_LINK_SENT;
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
