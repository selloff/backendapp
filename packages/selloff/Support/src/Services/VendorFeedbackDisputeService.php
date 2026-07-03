<?php

namespace App\Modules\Selloff\Support\Services;

use App\Models\User;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Models\FeedbackDispute;
use App\Modules\Selloff\Support\Support\FeedbackDisputeStatus;
use App\Modules\Selloff\Support\Support\FeedbackModerationStatus;

class VendorFeedbackDisputeService
{
    public function __construct(
        private readonly VendorFeedbackRatingService $ratings,
    ) {}

    public function open(Feedback $feedback, User $vendor, string $reason): FeedbackDispute
    {
        abort_if((int) $feedback->vendor_id !== (int) $vendor->id, 403);
        abort_unless($feedback->moderation_status === FeedbackModerationStatus::APPROVED, 422, 'Only approved feedback can be disputed.');
        abort_if($feedback->dispute()->exists(), 422, 'A dispute is already open for this feedback.');

        $dispute = FeedbackDispute::query()->create([
            'feedback_id' => $feedback->id,
            'vendor_id' => $vendor->id,
            'reason' => $reason,
            'status' => FeedbackDisputeStatus::OPEN,
        ]);

        $this->ratings->recomputeForVendor((int) $vendor->id);

        return $dispute->load('feedback');
    }

    public function resolve(FeedbackDispute $dispute, User $admin, string $resolution, ?string $note = null): FeedbackDispute
    {
        abort_unless($dispute->status === FeedbackDisputeStatus::OPEN, 422, 'Dispute is not open.');
        abort_unless(in_array($resolution, ['resolved', 'dismissed'], true), 422, 'Invalid resolution.');

        $dispute->update([
            'status' => $resolution,
            'admin_note' => $note,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        $this->ratings->recomputeForVendor((int) $dispute->vendor_id);

        return $dispute->fresh()->load(['feedback', 'vendor', 'resolver']);
    }
}
