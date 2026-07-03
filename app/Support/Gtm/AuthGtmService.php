<?php

namespace App\Support\Gtm;

use App\Models\User;
use Illuminate\Http\Request;

class AuthGtmService
{
    public function __construct(
        private readonly GtmEventFactory $factory,
    ) {}

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function userSignup(User $user, Request $request, string $channel = 'email'): array
    {
        return $this->factory->list('user_signup', [
            'user_id' => (string) $user->id,
            'first_name' => (string) $user->first_name,
            'last_name' => (string) $user->last_name,
            'username' => (string) ($user->username ?? $user->slug ?? ''),
            'email' => (string) $user->email,
            'phone' => (string) ($user->phone_number ?? ''),
            'channel' => $channel,
            'ip_address' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'date' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function userLogin(User $user, Request $request, string $channel = 'email'): array
    {
        return $this->factory->list('user_login', [
            'user_id' => (string) $user->id,
            'first_name' => (string) $user->first_name,
            'last_name' => (string) $user->last_name,
            'username' => (string) ($user->username ?? $user->slug ?? ''),
            'email' => (string) $user->email,
            'channel' => $channel,
            'ip_address' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'date' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return list<array{event: string, eventData: array<string, mixed>, timestamp: int}>
     */
    public function userChurn(User $user, Request $request): array
    {
        return $this->factory->list('user_churn', [
            'user_id' => (string) $user->id,
            'user_type' => $user->hasRole('vendor') ? 'seller' : 'buyer',
            'firstname' => (string) $user->first_name,
            'lastname' => (string) $user->last_name,
            'username' => (string) ($user->username ?? $user->slug ?? ''),
            'phone' => (string) ($user->phone_number ?? ''),
            'email' => (string) $user->email,
            'last_seen' => $user->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'date' => now()->toIso8601String(),
        ]);
    }
}
