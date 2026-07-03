<?php

namespace App\Support;

final class PlatformSettingsPublicFilter
{
    /** @var list<string> */
    private const SECRET_KEYS = [
        'facebook_app_secret',
        'google_client_secret',
        'vk_secure_key',
        'smtp_password',
        'brevo_api_key',
        'mailgun_api_key',
        'currency_converter_api_key',
        'turnstile_secret_key',
        'super_admin_pin_hash',
    ];

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function filter(array $settings): array
    {
        $filtered = [];

        foreach ($settings as $key => $value) {
            if (self::isSecretKey($key)) {
                continue;
            }

            if ($key === 'legacy_payment_gateways' && is_array($value)) {
                $filtered[$key] = array_map(
                    static fn ($gateway) => is_array($gateway)
                        ? self::filterPaymentGateway($gateway)
                        : $gateway,
                    $value,
                );

                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    private static function isSecretKey(string $key): bool
    {
        if (in_array($key, self::SECRET_KEYS, true)) {
            return true;
        }

        return str_ends_with($key, '_secret')
            || str_ends_with($key, '_secret_key')
            || str_ends_with($key, '_api_key')
            || str_ends_with($key, '_password');
    }

    /**
     * @param  array<string, mixed>  $gateway
     * @return array<string, mixed>
     */
    private static function filterPaymentGateway(array $gateway): array
    {
        unset($gateway['secret_key'], $gateway['webhook_secret']);

        return $gateway;
    }
}
