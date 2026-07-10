<?php

namespace App\Modules\Selloff\Support\Services;

use App\Modules\Selloff\Notification\Services\VendorFeedbackEmailService;
use App\Modules\Selloff\Support\Models\Feedback;

class VendorFeedbackNotificationService
{
    public function __construct(
        private readonly VendorFeedbackEmailService $emails,
    ) {}

    public function sendApprovedToVendor(Feedback $feedback): void
    {
        $this->emails->sendApproved($feedback);
    }
}
