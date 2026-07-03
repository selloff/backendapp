<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Http\Resources\Api\V1\FeedbackResource;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Models\FeedbackDispute;
use App\Modules\Selloff\Support\Services\VendorFeedbackModerationService;
use App\Modules\Selloff\Support\Support\FeedbackDisputeStatus;
use App\Modules\Selloff\Support\Support\FeedbackModerationStatus;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFeedbackController extends Controller
{
    public function __construct(private readonly VendorFeedbackModerationService $moderation) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('show', $request->integer('per_page', 15)), 100);

        $feedbacks = $this->filteredQuery($request)
            ->with([
                'user:id,first_name,last_name,email',
                'vendor:id,first_name,last_name,email',
                'replies.author:id,name',
                'dispute',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);

        $feedbacks->through(fn (Feedback $feedback) => new FeedbackResource($feedback));

        $payload = $feedbacks->toArray();
        $payload['counts'] = [
            'pending' => Feedback::query()->where('moderation_status', FeedbackModerationStatus::PENDING)->count(),
            'approved' => Feedback::query()->where('moderation_status', FeedbackModerationStatus::APPROVED)->count(),
            'rejected' => Feedback::query()->where('moderation_status', FeedbackModerationStatus::REJECTED)->count(),
            'disputes' => FeedbackDispute::query()->where('status', FeedbackDisputeStatus::OPEN)->count(),
        ];

        return ApiResponse::success($payload);
    }

    public function update(Request $request, Feedback $feedback): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required_without:status', 'in:approve,reject,hide,display'],
            'status' => ['required_without:action', 'in:unread,read,archived'],
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($data['action'])) {
            $feedback = match ($data['action']) {
                'approve', 'display' => $this->moderation->approve($feedback, $request->user()),
                'reject', 'hide' => $this->moderation->reject($feedback, $request->user(), $data['rejection_reason'] ?? null),
            };

            return ApiResponse::success(new FeedbackResource($feedback));
        }

        $feedback->update(['status' => $data['status']]);

        return ApiResponse::success(new FeedbackResource($feedback->fresh()->load(['user', 'vendor', 'replies', 'dispute'])));
    }

    /**
     * @return Builder<Feedback>
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = $request->string('q')->trim();
        $moderationStatus = $request->string('moderation_status')->trim();
        $status = $request->string('status')->trim();

        return Feedback::query()
            ->when($moderationStatus->isNotEmpty() && $moderationStatus->toString() !== 'all', fn ($q) => $q->where('moderation_status', $moderationStatus->toString()))
            ->when($status->isNotEmpty(), fn ($q) => $q->where('status', $status->toString()))
            ->when($search->isNotEmpty(), function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('feedback', 'ilike', '%'.$search.'%')
                        ->orWhere('title', 'ilike', '%'.$search.'%')
                        ->orWhere('feedback_type', 'ilike', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery->where('first_name', 'ilike', '%'.$search.'%')
                                ->orWhere('last_name', 'ilike', '%'.$search.'%')
                                ->orWhere('email', 'ilike', '%'.$search.'%');
                        })
                        ->orWhereHas('vendor', function ($vendorQuery) use ($search): void {
                            $vendorQuery->where('first_name', 'ilike', '%'.$search.'%')
                                ->orWhere('last_name', 'ilike', '%'.$search.'%')
                                ->orWhere('email', 'ilike', '%'.$search.'%');
                        });
                });
            });
    }
}
