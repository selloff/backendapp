<?php

namespace App\Modules\Selloff\Affiliate\Services;

use App\Models\User;
use App\Modules\Selloff\Catalog\Models\Product;
use App\Modules\Selloff\User\Models\ReferralProfile;

class AffiliateRateService
{
    public function __construct(
        private readonly AffiliateProgramSettingsService $program,
    ) {}

    /**
     * @return array{commission_rate: float, discount_rate: float}
     */
    public function ratesForProduct(Product $product): array
    {
        $settings = $this->program->programSettings();

        if (! filter_var($settings['status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return ['commission_rate' => 0.0, 'discount_rate' => 0.0];
        }

        if (($settings['type'] ?? '') === 'seller_based') {
            $sellerProfile = ReferralProfile::query()->where('user_id', $product->vendor_id)->first();
            $vendorStatus = (int) ($sellerProfile?->vendor_affiliate_status ?? 0);

            if ($vendorStatus === 0) {
                return ['commission_rate' => 0.0, 'discount_rate' => 0.0];
            }

            if ($vendorStatus === 2 && ! $product->is_affiliate) {
                return ['commission_rate' => 0.0, 'discount_rate' => 0.0];
            }

            return [
                'commission_rate' => (float) ($sellerProfile?->affiliate_commission_rate ?? 0),
                'discount_rate' => (float) ($sellerProfile?->affiliate_discount_rate ?? 0),
            ];
        }

        return [
            'commission_rate' => (float) ($settings['commission_rate'] ?? 0),
            'discount_rate' => (float) ($settings['discount_rate'] ?? 0),
        ];
    }

    public function canPromoteProduct(Product $product, User $referrer): bool
    {
        $settings = $this->program->programSettings();

        if (! filter_var($settings['status'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if ((int) ($referrer->is_affiliate ?? 0) !== 1) {
            return false;
        }

        if ($referrer->id === $product->vendor_id) {
            return false;
        }

        if (in_array($product->listing_type, ['ordinary_listing', 'bidding'], true)) {
            return false;
        }

        if ($product->is_free_product) {
            return false;
        }

        if (($settings['type'] ?? '') === 'seller_based') {
            $sellerProfile = ReferralProfile::query()->where('user_id', $product->vendor_id)->first();
            $vendorStatus = (int) ($sellerProfile?->vendor_affiliate_status ?? 0);

            if ($vendorStatus === 0) {
                return false;
            }

            if ($vendorStatus === 2 && ! $product->is_affiliate) {
                return false;
            }
        }

        return true;
    }
}
