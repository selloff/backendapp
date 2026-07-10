<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Services\TransactionalEmailService;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;

class WelcomeEmailService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
    ) {}

    public function queue(User $user): ?EmailJob
    {
        $email = trim((string) $user->email);

        if ($email === '') {
            return null;
        }

        return $this->email->queue(
            TransactionalEmailType::WELCOME,
            $email,
            [
                'firstname' => $this->firstName($user),
                'site_url' => rtrim((string) config('selloff.spa_url', config('app.url')), '/'),
                'user_id' => $user->id,
            ],
            subject: 'Welcome to Selloff',
            template: 'welcome',
        );
    }

    private function firstName(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));

        if ($first !== '') {
            return $first;
        }

        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : 'there';
    }
}
