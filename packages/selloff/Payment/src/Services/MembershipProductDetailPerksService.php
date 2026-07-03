<?php

namespace App\Modules\Selloff\Payment\Services;

use App\Models\User;
use App\Modules\Selloff\Payment\Support\MembershipEntitlementDefaults;
use App\Services\Platform\PlatformSettingsService;

class MembershipProductDetailPerksService
{
    /** @var array<int, array<string, bool>> */
    private array $detailPerksCache = [];

    public function __construct(
        private readonly MembershipEntitlementService $entitlements,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function isEnforced(): bool
    {
        return filter_var(
            $this->settings->all()['membership_plans_system'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * @return array{
     *     allow_website_link: bool,
     *     allow_social_links: bool,
     *     allow_whatsapp_link: bool,
     *     hide_seller_feedback: bool
     * }
     */
    public function detailPerks(User $vendor): array
    {
        if (isset($this->detailPerksCache[$vendor->id])) {
            return $this->detailPerksCache[$vendor->id];
        }

        $defaults = $this->defaultDetailPerks();

        if (! $this->isEnforced()) {
            return $this->detailPerksCache[$vendor->id] = $defaults;
        }

        $entitlements = $this->entitlements->effectiveEntitlements($vendor);
        if ($entitlements === null) {
            return $this->detailPerksCache[$vendor->id] = $defaults;
        }

        return $this->detailPerksCache[$vendor->id] = [
            'allow_website_link' => (bool) ($entitlements['allow_website_link'] ?? false),
            'allow_social_links' => (bool) ($entitlements['allow_social_links'] ?? false),
            'allow_whatsapp_link' => (bool) ($entitlements['allow_whatsapp_link'] ?? false),
            'hide_seller_feedback' => (bool) ($entitlements['hide_seller_feedback'] ?? false),
        ];
    }

    public function shouldHideSellerFeedback(User $vendor): bool
    {
        return $this->detailPerks($vendor)['hide_seller_feedback'];
    }

    /**
     * @return list<array{type: string, label: string, url: string}>
     */
    public function publicSocialLinks(User $vendor): array
    {
        $perks = $this->detailPerks($vendor);
        $raw = $this->resolveSocialRaw($vendor);
        $links = [];

        if ($perks['allow_website_link']) {
            $website = $this->firstNonEmpty($raw, ['website', 'personal_website_url', 'personal_website']);
            if ($website !== null) {
                $links[] = [
                    'type' => 'website',
                    'label' => 'Website',
                    'url' => $this->normalizeUrl($website),
                ];
            }
        }

        if ($perks['allow_social_links']) {
            foreach ($this->socialNetworkDefinitions() as $definition) {
                $url = $this->firstNonEmpty($raw, $definition['keys']);
                if ($url === null) {
                    continue;
                }

                $links[] = [
                    'type' => $definition['type'],
                    'label' => $definition['label'],
                    'url' => $this->normalizeUrl($url),
                ];
            }
        }

        if ($perks['allow_whatsapp_link']) {
            $whatsapp = $this->firstNonEmpty($raw, ['whatsapp_url', 'whatsapp']);
            if ($whatsapp === null) {
                $whatsapp = $this->whatsappUrlFromPhone($vendor->phone_number);
            }

            if ($whatsapp !== null) {
                $links[] = [
                    'type' => 'whatsapp',
                    'label' => 'WhatsApp',
                    'url' => $whatsapp,
                ];
            }
        }

        return $links;
    }

    /**
     * @return array{
     *     allow_website_link: bool,
     *     allow_social_links: bool,
     *     allow_whatsapp_link: bool,
     *     hide_seller_feedback: bool
     * }
     */
    private function defaultDetailPerks(): array
    {
        $defaults = MembershipEntitlementDefaults::planDefaults();

        return [
            'allow_website_link' => (bool) $defaults['allow_website_link'],
            'allow_social_links' => (bool) $defaults['allow_social_links'],
            'allow_whatsapp_link' => (bool) $defaults['allow_whatsapp_link'],
            'hide_seller_feedback' => (bool) $defaults['hide_seller_feedback'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSocialRaw(User $vendor): array
    {
        $profileData = $vendor->vendorProfile?->social_media_data;
        $userData = $vendor->social_media_data;

        return array_merge(
            is_array($userData) ? $userData : [],
            is_array($profileData) ? $profileData : [],
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  list<string>  $keys
     */
    private function firstNonEmpty(array $raw, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($raw[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<array{type: string, label: string, keys: list<string>}>
     */
    private function socialNetworkDefinitions(): array
    {
        return [
            ['type' => 'facebook', 'label' => 'Facebook', 'keys' => ['facebook', 'facebook_url']],
            ['type' => 'twitter', 'label' => 'Twitter', 'keys' => ['twitter', 'twitter_url']],
            ['type' => 'instagram', 'label' => 'Instagram', 'keys' => ['instagram', 'instagram_url']],
            ['type' => 'linkedin', 'label' => 'LinkedIn', 'keys' => ['linkedin', 'linkedin_url']],
            ['type' => 'youtube', 'label' => 'YouTube', 'keys' => ['youtube', 'youtube_url']],
            ['type' => 'tiktok', 'label' => 'TikTok', 'keys' => ['tiktok', 'tiktok_url']],
            ['type' => 'telegram', 'label' => 'Telegram', 'keys' => ['telegram', 'telegram_url']],
            ['type' => 'pinterest', 'label' => 'Pinterest', 'keys' => ['pinterest', 'pinterest_url']],
            ['type' => 'discord', 'label' => 'Discord', 'keys' => ['discord', 'discord_url']],
            ['type' => 'twitch', 'label' => 'Twitch', 'keys' => ['twitch', 'twitch_url']],
            ['type' => 'vk', 'label' => 'VK', 'keys' => ['vk', 'vk_url']],
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        if (preg_match('~^(?:f|ht)tps?://~i', $url) === 1) {
            return $url;
        }

        return 'https://'.$url;
    }

    private function whatsappUrlFromPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits;
    }
}
