<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;

class TurnstileVerificationService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $token, string $secretKey, ?string $remoteIp = null): bool
    {
        if ($token === '' || $secretKey === '') {
            return false;
        }

        try {
            $payload = [
                'secret' => $secretKey,
                'response' => $token,
            ];

            if ($remoteIp !== null && $remoteIp !== '') {
                $payload['remoteip'] = $remoteIp;
            }

            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, $payload);

            if (! $response->ok()) {
                return false;
            }

            return ($response->json('success') === true);
        } catch (\Throwable) {
            return false;
        }
    }
}
