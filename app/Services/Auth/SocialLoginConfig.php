<?php

namespace App\Services\Auth;

use App\Services\Platform\PlatformSettingsService;

class SocialLoginConfig
{
    /** @var array<string, array{platform_id: string, platform_secret: string, config_key: string}> */
    private const PROVIDERS = [
        'google' => [
            'platform_id' => 'google_client_id',
            'platform_secret' => 'google_client_secret',
            'config_key' => 'google',
        ],
        'facebook' => [
            'platform_id' => 'facebook_app_id',
            'platform_secret' => 'facebook_app_secret',
            'config_key' => 'facebook',
        ],
        'vkontakte' => [
            'platform_id' => 'vk_app_id',
            'platform_secret' => 'vk_secure_key',
            'config_key' => 'vkontakte',
        ],
    ];

    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function isEnabled(): bool
    {
        $platform = $this->settings->all();

        return filter_var($platform['social_login_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    public function providerConfigured(string $provider): bool
    {
        return filled($this->clientId($provider));
    }

    public function clientId(string $provider): ?string
    {
        $definition = self::PROVIDERS[$provider] ?? null;
        if ($definition === null) {
            return null;
        }

        $platform = $this->settings->all();
        $fromPlatform = $platform[$definition['platform_id']] ?? null;

        if (filled($fromPlatform)) {
            return (string) $fromPlatform;
        }

        $fromEnv = config("services.{$definition['config_key']}.client_id");

        return filled($fromEnv) ? (string) $fromEnv : null;
    }

    public function clientSecret(string $provider): ?string
    {
        $definition = self::PROVIDERS[$provider] ?? null;
        if ($definition === null) {
            return null;
        }

        $platform = $this->settings->all();
        $fromPlatform = $platform[$definition['platform_secret']] ?? null;

        if (filled($fromPlatform)) {
            return (string) $fromPlatform;
        }

        $fromEnv = config("services.{$definition['config_key']}.client_secret");

        return filled($fromEnv) ? (string) $fromEnv : null;
    }

    public function redirectUri(string $provider): string
    {
        $definition = self::PROVIDERS[$provider] ?? null;
        if ($definition === null) {
            return '';
        }

        return (string) config("services.{$definition['config_key']}.redirect");
    }

    public function redirectUriWarning(string $provider): ?string
    {
        $host = parse_url($this->redirectUri($provider), PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            return 'Google OAuth does not accept .local redirect URIs. Set GOOGLE_REDIRECT_URI in api.selloff/.env to a localhost callback (for example http://localhost:5173/api/v1/auth/oauth/google/callback with Vite proxy) and register that exact URI in Google Cloud Console.';
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function redirectUris(): array
    {
        $uris = [];

        foreach (array_keys(self::PROVIDERS) as $provider) {
            $uri = $this->redirectUri($provider);
            if ($uri !== '') {
                $uris[$provider] = $uri;
            }
        }

        return $uris;
    }

    public function applyProviderConfig(string $provider): void
    {
        $definition = self::PROVIDERS[$provider] ?? null;
        if ($definition === null) {
            return;
        }

        $configKey = $definition['config_key'];

        config([
            "services.{$configKey}.client_id" => $this->clientId($provider),
            "services.{$configKey}.client_secret" => $this->clientSecret($provider),
            "services.{$configKey}.redirect" => $this->redirectUri($provider),
        ]);
    }

    /**
     * @return array{enabled: bool, google: bool, facebook: bool, vkontakte: bool}
     */
    public function flags(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'google' => $this->providerConfigured('google'),
            'facebook' => $this->providerConfigured('facebook'),
            'vkontakte' => $this->providerConfigured('vkontakte'),
        ];
    }

    public function assertProviderConfigured(string $provider): void
    {
        abort_unless(
            $this->providerConfigured($provider),
            422,
            'Social login provider is not configured.',
        );
    }
}
