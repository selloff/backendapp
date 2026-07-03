<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Support\Models\Feedback;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeedbackAbuseReportController extends Controller
{
    public function store(Request $request, Feedback $feedback): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:5', 'max:10000'],
            'context' => ['sometimes', Rule::in(['vendor', 'buyer'])],
        ]);

        $user = $request->user();
        abort_if($user === null, 401);

        if (($data['context'] ?? null) === 'vendor') {
            abort_unless((int) $feedback->vendor_id === (int) $user->id, 403);
        }

        abort_if((int) $feedback->user_id === (int) $user->id, 422, 'You cannot report your own feedback.');

        DB::table('abuse_reports')->insert([
            'reporter_id' => $user->id,
            'product_id' => null,
            'user_id' => $feedback->vendor_id,
            'item_id' => $feedback->id,
            'report_type' => 'feedback',
            'description' => $data['description'],
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::success(message: 'Report submitted.');
    }
}
