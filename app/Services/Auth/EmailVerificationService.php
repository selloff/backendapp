<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Modules\Selloff\Notification\Models\EmailJob;
use App\Modules\Selloff\Notification\Services\TransactionalEmailService;
use App\Modules\Selloff\Notification\Support\TransactionalEmailType;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function __construct(
        private readonly TransactionalEmailService $email,
        private readonly WelcomeEmailService $welcome,
    ) {}

    public function issueToken(User $user): EmailVerificationToken
    {
        EmailVerificationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->delete();

        return EmailVerificationToken::query()->create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDay(),
        ]);
    }

    public function queueVerificationEmail(User $user, EmailVerificationToken $token): ?EmailJob
    {
        $confirmUrl = rtrim((string) config('selloff.spa_url', config('app.url')), '/')
            .'/confirm-email?token='.$token->token;

        return $this->email->queue(
            TransactionalEmailType::ACTIVATION,
            (string) $user->email,
            [
                'title' => 'Confirm your account',
                'content' => 'Thanks for joining Selloff. Please confirm your email address to activate your account and start buying and selling securely.',
                'url' => $confirmUrl,
                'buttonText' => 'Confirm your account',
                'user_id' => $user->id,
                'token' => $token->token,
            ],
            subject: 'Confirm your account',
            template: 'main',
        );
    }

    public function verify(string $token): User
    {
        $record = EmailVerificationToken::query()
            ->where('token', $token)
            ->whereNull('verified_at')
            ->first();

        if (! $record || $record->isExpired()) {
            throw ValidationException::withMessages([
                'token' => ['Verification link is invalid or has expired.'],
            ]);
        }

        $user = $record->user;
        $alreadyVerified = $user->email_verified_at !== null;
        $user->update(['email_verified_at' => now()]);
        $record->update(['verified_at' => now()]);

        $fresh = $user->fresh();

        if (! $alreadyVerified) {
            $this->welcome->queue($fresh);
        }

        return $fresh;
    }
}
