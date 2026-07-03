<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Services\AdminAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function show(Request $request, AdminAnalyticsService $analytics): JsonResponse
    {
        return ApiResponse::success($analytics->build($request));
    }
}
