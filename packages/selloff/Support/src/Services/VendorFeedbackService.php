<?php

namespace App\Modules\Selloff\Support\Services;

use App\Models\User;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Support\FeedbackModerationStatus;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\UploadedFile;

class VendorFeedbackService
{
    public function __construct(
        private readonly VendorFeedbackEligibilityService $eligibility,
        private readonly MediaUploadService $mediaUpload,
    ) {}

    /**
     * @param  array{feedback_type: string, feedback: string, rating?: int|null, remove_image?: bool}  $data
     */
    public function upsert(User $author, User $vendor, array $data, ?UploadedFile $image = null): Feedback
    {
        $this->eligibility->assertCanLeaveFeedback($author, $vendor);

        $existing = Feedback::query()
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $author->id)
            ->first();

        $wasApproved = $existing?->moderation_status === FeedbackModerationStatus::APPROVED;

        $payload = [
            'rating' => $data['rating'] ?? null,
            'feedback_type' => $data['feedback_type'],
            'feedback' => $data['feedback'],
            'status' => 'unread',
            'moderation_status' => FeedbackModerationStatus::PENDING,
            'edited_at' => now(),
            'approved_at' => null,
            'approved_by' => null,
            'rejection_reason' => null,
        ];

        if ($image !== null) {
            $upload = $this->mediaUpload->upload($image, 'feedback_image');
            $payload['image_path'] = $upload['path'];
            $payload['image_disk'] = $upload['disk'];
        } elseif (! empty($data['remove_image']) && $existing !== null) {
            $payload['image_path'] = null;
            $payload['image_disk'] = null;
        } elseif ($existing !== null) {
            $payload['image_path'] = $existing->image_path;
            $payload['image_disk'] = $existing->image_disk;
        }

        if ($existing === null) {
            $feedback = Feedback::query()->create(array_merge($payload, [
                'vendor_id' => $vendor->id,
                'user_id' => $author->id,
            ]));
        } else {
            if (! $wasApproved) {
                unset($payload['approved_at'], $payload['approved_by'], $payload['rejection_reason']);
            }
            $existing->update($payload);
            $feedback = $existing->fresh();
        }

        return $feedback->load(['user:id,first_name,last_name,email,slug,avatar', 'replies.author:id,name', 'dispute']);
    }

    public function mineForVendor(User $author, User $vendor): ?Feedback
    {
        return Feedback::query()
            ->with(['replies.author:id,name', 'dispute'])
            ->where('vendor_id', $vendor->id)
            ->where('user_id', $author->id)
            ->first();
    }

    public function listApprovedForVendor(User $vendor, int $perPage = 15)
    {
        return Feedback::query()
            ->approved()
            ->with(['user:id,first_name,last_name,email,slug,avatar', 'replies.author:id,name', 'dispute'])
            ->where('vendor_id', $vendor->id)
            ->latest('id')
            ->paginate($perPage);
    }
}
