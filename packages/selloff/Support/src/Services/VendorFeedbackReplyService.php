<?php

namespace App\Modules\Selloff\Support\Services;

use App\Models\User;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Models\FeedbackReply;
use App\Modules\Selloff\Support\Support\FeedbackModerationStatus;

class VendorFeedbackReplyService
{
    public function addVendorReply(Feedback $feedback, User $vendor, string $body): FeedbackReply
    {
        abort_if((int) $feedback->vendor_id !== (int) $vendor->id, 403);
        abort_unless($feedback->moderation_status === FeedbackModerationStatus::APPROVED, 422, 'You can only reply to approved feedback.');
        abort_if(
            FeedbackReply::query()->where('feedback_id', $feedback->id)->where('author_role', 'vendor')->exists(),
            422,
            'You have already replied to this feedback.',
        );

        return FeedbackReply::query()->create([
            'feedback_id' => $feedback->id,
            'author_id' => $vendor->id,
            'author_role' => 'vendor',
            'body' => $body,
        ]);
    }

    public function addBuyerFollowUp(Feedback $feedback, User $buyer, string $body): FeedbackReply
    {
        abort_if((int) $feedback->user_id !== (int) $buyer->id, 403);
        abort_unless($feedback->moderation_status === FeedbackModerationStatus::APPROVED, 422, 'You can only reply to approved feedback.');
        abort_unless(
            FeedbackReply::query()->where('feedback_id', $feedback->id)->where('author_role', 'vendor')->exists(),
            422,
            'The seller must reply before you can follow up.',
        );
        abort_if(
            FeedbackReply::query()->where('feedback_id', $feedback->id)->where('author_role', 'buyer')->exists(),
            422,
            'You have already posted a follow-up reply.',
        );

        return FeedbackReply::query()->create([
            'feedback_id' => $feedback->id,
            'author_id' => $buyer->id,
            'author_role' => 'buyer',
            'body' => $body,
        ]);
    }
}
