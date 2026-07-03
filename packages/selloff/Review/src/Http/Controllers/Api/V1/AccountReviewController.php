<?php

namespace App\Modules\Selloff\Review\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Review\Models\ProductReview;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reviews = ProductReview::query()
            ->with(['product.translations'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        return ApiResponse::success($reviews);
    }
}
