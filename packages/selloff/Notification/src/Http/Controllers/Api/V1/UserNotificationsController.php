<?php

namespace App\Modules\Selloff\Notification\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Notification\Services\UserNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationsController extends Controller
{
    public function index(Request $request, UserNotificationService $notifications): JsonResponse
    {
        return ApiResponse::success($notifications->build($request));
    }

    public function unreadCount(Request $request, UserNotificationService $notifications): JsonResponse
    {
        return ApiResponse::success([
            'count' => $notifications->unreadCount($request),
        ]);
    }

    public function markRead(Request $request, string $key, UserNotificationService $notifications): JsonResponse
    {
        $notifications->markRead($request, $key);

        return ApiResponse::success(null, 200, 'Notification marked as read.');
    }

    public function markAllRead(Request $request, UserNotificationService $notifications): JsonResponse
    {
        $marked = $notifications->markAllRead($request);

        return ApiResponse::success([
            'marked' => $marked,
        ], 200, 'All notifications marked as read.');
    }
}
