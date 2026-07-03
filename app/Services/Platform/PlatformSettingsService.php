<?php

namespace App\Services\Platform;

use App\Models\PlatformSetting;
use App\Support\PlatformSettingsNormalizer;
use Illuminate\Support\Facades\Cache;

class PlatformSettingsService
{
    private const CACHE_KEY = 'selloff.platform_settings';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $settings = PlatformSetting::query()->get(['key', 'value']);

            $map = config('selloff.platform_settings', []);
            foreach ($settings as $setting) {
                $map[$setting->key] = $setting->value;
            }

            return PlatformSettingsNormalizer::normalize($map);
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function upsertMany(array $values, ?string $group = 'general'): void
    {
        foreach ($values as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
