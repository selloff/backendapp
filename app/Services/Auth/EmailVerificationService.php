<?php

namespace App\Services\Auth;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Modules\Selloff\Notification\Models\EmailJob;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
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

    public function queueVerificationEmail(User $user, EmailVerificationToken $token): EmailJob
    {
        $confirmUrl = rtrim((string) config('app.spa_url', config('app.url')), '/')
            .'/confirm-email?token='.$token->token;

        return EmailJob::query()->create([
            'to_email' => $user->email,
            'subject' => 'Confirm your email address',
            'body' => 'Please confirm your email by visiting: '.$confirmUrl,
            'status' => 'pending',
            'metadata' => [
                'type' => 'email_verification',
                'user_id' => $user->id,
                'token' => $token->token,
            ],
        ]);
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
        $user->update(['email_verified_at' => now()]);
        $record->update(['verified_at' => now()]);

        return $user->fresh();
    }
}
