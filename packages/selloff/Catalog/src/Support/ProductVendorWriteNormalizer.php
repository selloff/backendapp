<?php

namespace App\Modules\Selloff\Catalog\Support;

use App\Services\Platform\PlatformSettingsService;

class ProductVendorWriteNormalizer
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data, ?string $type = null): array
    {
        if (array_key_exists('no_vat', $data)) {
            if ($data['no_vat']) {
                $data['vat_rate'] = null;
            }
            unset($data['no_vat']);
        }

        if (! empty($data['is_free_product'])) {
            $data['price'] = 0;
            $data['price_discounted'] = null;
        }

        if (! $this->vatEnabled()) {
            unset($data['vat_rate']);
        }

        $productType = $type ?? ($data['type'] ?? null);
        if ($productType === 'digital' && empty($data['is_free_product']) && isset($data['price']) && (float) $data['price'] <= 0) {
            $data['is_free_product'] = true;
            $data['price_discounted'] = null;
        }

        return $data;
    }

    public function vatEnabled(): bool
    {
        $settings = $this->platformSettings->all();
        $value = $settings['vat_status'] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}
