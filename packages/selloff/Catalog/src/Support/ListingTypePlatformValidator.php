<?php

namespace App\Modules\Selloff\Catalog\Support;

use App\Services\Platform\PlatformSettingsService;
use Illuminate\Validation\Validator;

class ListingTypePlatformValidator
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function configure(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->inputString($validator, 'type');
            $listingType = $this->inputString($validator, 'listing_type');

            if ($listingType === null && $type === null) {
                return;
            }

            $this->assertAllowed($validator, $type, $listingType);
        });
    }

    public function assertAllowed(Validator $validator, ?string $type, ?string $listingType): void
    {
        $settings = $this->platformSettings->all();

        if ($type !== null) {
            if ($type === 'physical' && ! $this->platformBool($settings, 'physical_products_enabled', true)) {
                $validator->errors()->add('type', 'Physical products are disabled on this marketplace.');

                return;
            }

            if ($type === 'digital' && ! $this->platformBool($settings, 'digital_products_enabled', true)) {
                $validator->errors()->add('type', 'Digital products are disabled on this marketplace.');

                return;
            }
        }

        if ($listingType === null) {
            return;
        }

        if ($listingType === 'ordinary_listing' && ! $this->platformBool($settings, 'classified_ads_enabled', true)) {
            $validator->errors()->add('listing_type', 'Classified listings are disabled on this marketplace.');

            return;
        }

        if ($listingType === 'bidding' && ! $this->platformBool($settings, 'bidding_enabled', true)) {
            $validator->errors()->add('listing_type', 'Quote request listings are disabled on this marketplace.');

            return;
        }

        if ($listingType === 'license_key' && ! $this->platformBool($settings, 'license_keys_enabled', false)) {
            $validator->errors()->add('listing_type', 'License key listings are disabled on this marketplace.');

            return;
        }

        if ($listingType === 'sell_on_site') {
            $allowed = $type === 'digital'
                ? $this->platformBool($settings, 'digital_products_enabled', true)
                : ($this->platformBool($settings, 'marketplace_enabled', true)
                    || $this->platformBool($settings, 'multi_vendor_system', true));

            if (! $allowed) {
                $validator->errors()->add('listing_type', 'Buy-now listings are disabled on this marketplace.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function platformBool(array $settings, string $key, bool $default): bool
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

    private function inputString(Validator $validator, string $key): ?string
    {
        $value = $validator->getData()[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
