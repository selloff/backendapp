<?php

namespace App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Selloff\Admin\Services\AdminNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationsController extends Controller
{
    public function index(Request $request, AdminNotificationService $notifications): JsonResponse
    {
        return ApiResponse::success($notifications->build($request));
    }

    public function unreadCount(Request $request, AdminNotificationService $notifications): JsonResponse
    {
        return ApiResponse::success([
            'count' => $notifications->unreadCount($request),
        ]);
    }

    public function markRead(Request $request, string $key, AdminNotificationService $notifications): JsonResponse
    {
        $notifications->markRead($request, $key);

        return ApiResponse::success(null, 200, 'Notification marked as read.');
    }

    public function markAllRead(Request $request, AdminNotificationService $notifications): JsonResponse
    {
        $marked = $notifications->markAllRead($request);

        return ApiResponse::success([
            'marked' => $marked,
        ], 200, 'All notifications marked as read.');
    }
}
