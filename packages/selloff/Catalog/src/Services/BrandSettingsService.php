<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Services\Platform\PlatformSettingsService;

class BrandSettingsService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    /**
     * @return array{brand_status: bool, is_brand_optional: bool, brand_where_to_display: int}
     */
    public function all(): array
    {
        $settings = $this->platformSettings->all();

        return [
            'brand_status' => $this->bool($settings, 'brand_status', false),
            'is_brand_optional' => $this->bool($settings, 'is_brand_optional', true),
            'brand_where_to_display' => $this->int($settings, 'brand_where_to_display', 2, [1, 2]),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->all()['brand_status'];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function bool(array $settings, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $settings)) {
            return $default;
        }

        $value = $settings[$key];

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<int>  $allowed
     */
    private function int(array $settings, string $key, int $default, array $allowed): int
    {
        $value = (int) ($settings[$key] ?? $default);

        return in_array($value, $allowed, true) ? $value : $default;
    }
}
