<?php

namespace App\Support;

final class PlatformSettingsNormalizer
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function normalize(array $settings): array
    {
        if (array_key_exists('primary_color', $settings)) {
            $settings['primary_color'] = BrandColor::normalize(
                is_string($settings['primary_color']) ? $settings['primary_color'] : null,
            );
        }

        $imagePrefix = MediaUrl::prefixForSettings($settings);

        foreach (['site_logo_url', 'site_logo_email_url', 'site_favicon_url'] as $key) {
            if (! array_key_exists($key, $settings) || ! is_string($settings[$key]) || $settings[$key] === '') {
                continue;
            }

            $resolved = MediaUrl::resolve($settings[$key], $imagePrefix);
            if ($resolved !== null) {
                $settings[$key] = $resolved;
            }
        }

        if (config('selloff.security.turnstile_disabled', false)) {
            $settings['turnstile_status'] = false;
        }

        return LegacyEditorHtml::normalizeSettings($settings);
    }
}
