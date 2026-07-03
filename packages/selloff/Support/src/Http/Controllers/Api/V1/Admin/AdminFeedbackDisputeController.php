<?php

namespace App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Http\Resources\Api\V1\FeedbackResource;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Modules\Selloff\Support\Models\FeedbackDispute;
use App\Modules\Selloff\Support\Services\VendorFeedbackDisputeService;
use App\Modules\Selloff\Support\Services\VendorFeedbackModerationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFeedbackDisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $disputes = FeedbackDispute::query()
            ->with(['feedback.user:id,name', 'feedback.vendor:id,name', 'vendor:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($disputes);
    }

    public function update(
        Request $request,
        FeedbackDispute $feedbackDispute,
        VendorFeedbackDisputeService $disputes,
    ): JsonResponse {
        $data = $request->validate([
            'resolution' => ['required', 'in:resolved,dismissed'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $dispute = $disputes->resolve(
            $feedbackDispute,
            $request->user(),
            $data['resolution'],
            $data['admin_note'] ?? null,
        );

        return ApiResponse::success($dispute);
    }
}
