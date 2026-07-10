<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Modules\Selloff\Notification\Services\TransactionalEmailService;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;

class PasswordResetEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
    ) {}

    public function send(User $user, string $token): void
    {
        $email = trim((string) ($user->email ?? ''));

        if ($email === '') {
            return;
        }

        $url = rtrim((string) config('selloff.spa_url', config('app.url')), '/')
            .'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $email,
            ]);

        $this->email->sendNow(
            TransactionalEmailType::RESET_PASSWORD,
            $email,
            [
                'title' => 'Reset your password',
                'content' => 'We received a request to reset the password for your Selloff account. '
                    .'This link will expire in 60 minutes. If you did not request a reset, you can ignore this email.',
                'url' => $url,
                'buttonText' => 'Reset password',
            ],
            subject: 'Reset your Selloff password',
            template: 'main',
        );
    }
}
