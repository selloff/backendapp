<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Http\Resources\Api\V1\FeedbackResource;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Services\VendorFeedbackReplyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountVendorFeedbackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $feedbacks = Feedback::query()
            ->with(['vendor:id,name', 'vendor.vendorProfile:user_id,shop_name,slug', 'replies.author:id,name', 'dispute'])
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(min($request->integer('per_page', 15), 48));

        $feedbacks->through(fn (Feedback $feedback) => new FeedbackResource($feedback));

        return ApiResponse::success($feedbacks);
    }

    public function reply(Request $request, Feedback $feedback, VendorFeedbackReplyService $replies): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $reply = $replies->addBuyerFollowUp($feedback, $request->user(), $data['body']);

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
}
