<?php

namespace App\Modules\Selloff\Support\Services;

use App\Models\User;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Support\FeedbackModerationStatus;

class VendorFeedbackModerationService
{
    public function __construct(
        private readonly VendorFeedbackRatingService $ratings,
        private readonly VendorFeedbackNotificationService $notifications,
    ) {}

    public function approve(Feedback $feedback, User $admin): Feedback
    {
        abort_unless(
            in_array($feedback->moderation_status, [FeedbackModerationStatus::PENDING, FeedbackModerationStatus::REJECTED], true),
            422,
            'Feedback is not eligible to be displayed.',
        );

        $feedback->update([
            'moderation_status' => FeedbackModerationStatus::APPROVED,
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'rejection_reason' => null,
            'status' => $feedback->status ?: 'unread',
        ]);

        $feedback = $feedback->fresh()->load(['user', 'vendor', 'replies', 'dispute']);

        $this->ratings->recomputeForVendor((int) $feedback->vendor_id);
        $this->notifications->sendApprovedToVendor($feedback);

        return $feedback;
    }

    public function reject(Feedback $feedback, User $admin, ?string $reason = null): Feedback
    {
        abort_unless(
            in_array($feedback->moderation_status, [FeedbackModerationStatus::PENDING, FeedbackModerationStatus::APPROVED], true),
            422,
            'Feedback cannot be hidden.',
        );

        $wasApproved = $feedback->moderation_status === FeedbackModerationStatus::APPROVED;

        $feedback->update([
            'moderation_status' => FeedbackModerationStatus::REJECTED,
            'rejection_reason' => $reason,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        if ($wasApproved) {
            $this->ratings->recomputeForVendor((int) $feedback->vendor_id);
        }

        return $feedback->fresh()->load(['user', 'vendor', 'replies', 'dispute']);
    }
}
