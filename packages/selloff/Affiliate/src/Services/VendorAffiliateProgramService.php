<?php

namespace App\Modules\Selloff\Affiliate\Services;

use App\Modules\Selloff\User\Models\ReferralProfile;

class VendorAffiliateProgramService
{
    public function __construct(
        private readonly AffiliateProgramSettingsService $program,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function programSettings(): array
    {
        return $this->program->programSettings();
    }

    public function programEnabled(): bool
    {
        return filter_var($this->programSettings()['status'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function isSellerBasedProgram(): bool
    {
        return ($this->programSettings()['type'] ?? '') === 'seller_based';
    }

    public function canManageProductAffiliate(ReferralProfile $profile): bool
    {
        return $this->programEnabled()
            && $this->isSellerBasedProgram()
            && (int) $profile->vendor_affiliate_status === 2;
    }
}
