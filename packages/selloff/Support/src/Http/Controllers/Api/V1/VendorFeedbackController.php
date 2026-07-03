<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Http\Resources\Api\V1\FeedbackResource;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Services\VendorFeedbackDisputeService;
use App\Modules\Selloff\Support\Services\VendorFeedbackReplyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorFeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $feedbacks = Feedback::query()
            ->with(['user:id,first_name,last_name,email', 'replies.author:id,name', 'dispute'])
            ->where('vendor_id', $request->user()->id)
            ->where('moderation_status', 'approved')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(20);

        $feedbacks->through(fn (Feedback $feedback) => new FeedbackResource($feedback));

        return ApiResponse::success($feedbacks);
    }

    public function update(Request $request, Feedback $feedback): JsonResponse
    {
        abort_unless($feedback->vendor_id === $request->user()->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:unread,read,archived'],
        ]);

        $feedback->update(['status' => $data['status']]);

        return ApiResponse::success(new FeedbackResource($feedback->fresh()->load(['user', 'replies', 'dispute'])));
    }

    public function reply(Request $request, Feedback $feedback, VendorFeedbackReplyService $replies): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $reply = $replies->addVendorReply($feedback, $request->user(), $data['body']);

        return ApiResponse::success([
            'reply' => [
                'id' => $reply->id,
                'author_role' => $reply->author_role,
                'body' => $reply->body,
                'created_at' => $reply->created_at,
            ],
            'feedback' => new FeedbackResource($feedback->fresh()->load(['user', 'replies.author', 'dispute'])),
        ]);
    }

    public function dispute(Request $request, Feedback $feedback, VendorFeedbackDisputeService $disputes): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $dispute = $disputes->open($feedback, $request->user(), $data['reason']);

        return ApiResponse::success([
            'dispute' => $dispute,
            'feedback' => new FeedbackResource($feedback->fresh()->load(['user', 'replies', 'dispute'])),
        ], 201);
    }
}
