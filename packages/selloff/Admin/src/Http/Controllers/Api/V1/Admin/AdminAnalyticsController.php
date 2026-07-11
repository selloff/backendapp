<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Services\AdminAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminAnalyticsController extends Controller
{
    public function show(Request $request, AdminAnalyticsService $analytics): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $ttl = max(0, (int) config('selloff.admin.analytics_cache_seconds', 300));
        $cacheKey = 'admin:analytics:'.hash('xxh128', json_encode([
            'user_id' => $user->getKey(),
            'period' => $request->query('period'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ], JSON_THROW_ON_ERROR));

        if ($request->boolean('refresh')) {
            Cache::forget($cacheKey);
        }

        $payload = $ttl === 0
            ? $analytics->build($request)
            : Cache::remember($cacheKey, $ttl, fn (): array => $analytics->build($request));

        return ApiResponse::success($payload);
    }
}
