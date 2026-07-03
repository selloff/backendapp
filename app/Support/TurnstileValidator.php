<?php

namespace App\Support;

use App\Services\Auth\TurnstileVerificationService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Validation\Validator;

final class TurnstileValidator
{
    public static function isEnabled(): bool
    {
        if (config('selloff.security.turnstile_disabled', false)) {
            return false;
        }

        $settings = app(PlatformSettingsService::class)->all();

        return PlatformSettingValue::bool($settings['turnstile_status'] ?? false);
    }

    public static function validateToken(?string $token, ?string $remoteIp = null): ?string
    {
        if (! self::isEnabled()) {
            return null;
        }

        $token = PlatformSettingValue::string($token);

        if ($token === '') {
            return 'Verification is required.';
        }

        $settings = app(PlatformSettingsService::class)->all();
        $secretKey = PlatformSettingValue::string($settings['turnstile_secret_key'] ?? '');

        if (! app(TurnstileVerificationService::class)->verify($token, $secretKey, $remoteIp)) {
            return 'Bot verification failed. Please try again.';
        }

        return null;
    }

    public static function appendToValidator(Validator $validator, ?string $token, ?string $remoteIp, bool $required): void
    {
        $validator->after(function (Validator $validator) use ($token, $remoteIp, $required): void {
            if (! $required || ! self::isEnabled()) {
                return;
            }

            $error = self::validateToken($token, $remoteIp);

            if ($error !== null) {
                $validator->errors()->add('cf_turnstile_response', $error);
            }
        });
    }
}
