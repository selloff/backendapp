<?php

namespace App\Modules\Selloff\Support\Services;

use App\Models\User;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Support\FeedbackModerationStatus;
use App\Modules\Selloff\User\Models\VendorProfile;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\UploadedFile;

class VendorFeedbackRatingService
{
    public function recomputeForVendor(int $vendorId): void
    {
        $profile = VendorProfile::query()->where('user_id', $vendorId)->first();
        if ($profile === null) {
            return;
        }

        $query = Feedback::query()
            ->approved()
            ->where('vendor_id', $vendorId)
            ->whereDoesntHave('dispute', fn ($q) => $q->where('status', 'open'));

        $positive = (clone $query)->where('feedback_type', 'positive')->count();
        $neutral = (clone $query)->where('feedback_type', 'neutral')->count();
        $negative = (clone $query)->where('feedback_type', 'negative')->count();
        $total = $positive + $neutral + $negative;

        $avgRating = (clone $query)->whereNotNull('rating')->avg('rating');

        $profile->update([
            'feedback_positive_count' => $positive,
            'feedback_neutral_count' => $neutral,
            'feedback_negative_count' => $negative,
            'feedback_total_count' => $total,
            'feedback_percent_positive' => $total > 0 ? round(($positive / $total) * 100, 2) : 0,
            'feedback_avg_rating' => $avgRating !== null ? round((float) $avgRating, 2) : null,
        ]);
    }

    public function recomputeAll(): int
    {
        $count = 0;
        VendorProfile::query()->orderBy('id')->chunkById(100, function ($profiles) use (&$count): void {
            foreach ($profiles as $profile) {
                $this->recomputeForVendor((int) $profile->user_id);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @return array<string, int|float|null>
     */
    public function summaryForVendor(int $vendorId): array
    {
        $profile = VendorProfile::query()->where('user_id', $vendorId)->first();

        if ($profile === null) {
            return [
                'positive_count' => 0,
                'neutral_count' => 0,
                'negative_count' => 0,
                'total_count' => 0,
                'percent_positive' => 0,
                'avg_rating' => null,
            ];
        }

        return [
            'positive_count' => (int) $profile->feedback_positive_count,
            'neutral_count' => (int) $profile->feedback_neutral_count,
            'negative_count' => (int) $profile->feedback_negative_count,
            'total_count' => (int) $profile->feedback_total_count,
            'percent_positive' => (float) $profile->feedback_percent_positive,
            'avg_rating' => $profile->feedback_avg_rating !== null ? (float) $profile->feedback_avg_rating : null,
        ];
    }
}
