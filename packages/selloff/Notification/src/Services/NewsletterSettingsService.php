<?php

namespace App\Modules\Selloff\Notification\Services;

use App\Services\Platform\PlatformSettingsService;
use App\Support\MediaUrl;

class NewsletterSettingsService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $stored = $this->settings->all();
        $path = $this->imagePath($stored);

        return [
            'newsletter_status' => $this->bool($stored, 'newsletter_status', true),
            'newsletter_popup_active' => $this->bool($stored, 'newsletter_popup_active', false),
            'newsletter_image_path' => $path,
            'newsletter_image_storage' => (string) ($stored['newsletter_image_storage'] ?? ''),
            'newsletter_image_url' => $path ? MediaUrl::resolve($path) : (string) ($stored['newsletter_image_url'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(array $payload): array
    {
        $updates = [];

        foreach (['newsletter_status', 'newsletter_popup_active'] as $key) {
            if (array_key_exists($key, $payload)) {
                $updates[$key] = filter_var($payload[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (array_key_exists('newsletter_image_path', $payload)) {
            $updates['newsletter_image_path'] = $payload['newsletter_image_path'] !== null && $payload['newsletter_image_path'] !== ''
                ? (string) $payload['newsletter_image_path']
                : null;
        }

        if (array_key_exists('newsletter_image_storage', $payload)) {
            $updates['newsletter_image_storage'] = $payload['newsletter_image_storage'] !== null
                ? (string) $payload['newsletter_image_storage']
                : '';
        }

        if (array_key_exists('newsletter_image_url', $payload)) {
            $updates['newsletter_image_url'] = $payload['newsletter_image_url'] !== null
                ? (string) $payload['newsletter_image_url']
                : '';
        }

        if ($updates !== []) {
            $this->settings->upsertMany($updates, 'general');
        }

        return $this->settings();
    }

    /**
     * @param  array<string, mixed>  $legacySettings
     */
    public function importFromLegacy(array $legacySettings): void
    {
        $image = $legacySettings['image'] ?? null;

        $this->settings->upsertMany([
            'newsletter_status' => $this->legacyBool($legacySettings['status'] ?? false),
            'newsletter_popup_active' => $this->legacyBool($legacySettings['is_popup_active'] ?? false),
            'newsletter_image_path' => is_string($image) && $image !== '' && $image !== '0' ? $image : null,
            'newsletter_image_storage' => (string) ($legacySettings['storage'] ?? 'local'),
        ], 'general');
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function imagePath(array $stored): ?string
    {
        $path = $stored['newsletter_image_path'] ?? null;

        if (is_string($path) && $path !== '' && $path !== '0') {
            return $path;
        }

        $legacyUrl = $stored['newsletter_image_url'] ?? null;

        return is_string($legacyUrl) && $legacyUrl !== '' ? $legacyUrl : null;
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function bool(array $stored, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $stored)) {
            return $default;
        }

        $value = $stored[$key];

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function legacyBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
