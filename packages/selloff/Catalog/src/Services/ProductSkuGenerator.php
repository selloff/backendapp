<?php

namespace App\Modules\Selloff\Catalog\Services;

use App\Modules\Selloff\Catalog\Models\Product;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Support\Str;

class ProductSkuGenerator
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function shouldGenerate(?string $sku, string $listingType): bool
    {
        if ($sku !== null && trim($sku) !== '') {
            return false;
        }

        if (! $this->isSkuFeatureEnabled()) {
            return false;
        }

        return in_array($listingType, ['ordinary_listing', 'sell_on_site', 'license_key'], true);
    }

    public function generate(int $vendorId): string
    {
        do {
            $sku = 'SKU-'.Str::upper(Str::random(8));
        } while (
            Product::query()
                ->where('vendor_id', $vendorId)
                ->where('sku', $sku)
                ->exists()
        );

        return $sku;
    }

    private function isSkuFeatureEnabled(): bool
    {
        $settings = $this->platformSettings->all();
        $value = $settings['marketplace_sku'] ?? true;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
