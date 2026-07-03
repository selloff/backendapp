<?php

namespace App\Modules\Selloff\Support\Services;

use App\Models\User;
use App\Services\Platform\PlatformSettingsService;

class VendorFeedbackEligibilityService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function assertCanLeaveFeedback(User $author, User $vendor): void
    {
        abort_unless($this->isEnabled(), 422, 'Vendor feedback is disabled on this marketplace.');

        abort_if((int) $author->id === (int) $vendor->id, 422, 'You cannot leave feedback on your own shop.');
    }

    public function isEnabled(): bool
    {
        $settings = $this->platformSettings->all();

        return (bool) ($settings['vendor_feedback_enabled'] ?? true);
    }
}
