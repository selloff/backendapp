<?php

use App\Modules\Selloff\Notification\Http\Controllers\Api\V1\Admin\AdminNewsletterController;
use App\Modules\Selloff\Notification\Http\Controllers\Api\V1\DeviceTokenController;
use App\Modules\Selloff\Notification\Http\Controllers\Api\V1\NewsletterController;
use App\Modules\Selloff\Notification\Http\Controllers\Api\V1\UserNotificationsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('notifications')->group(function (): void {
    Route::get('/inbox', [UserNotificationsController::class, 'index']);
    Route::get('/unread-count', [UserNotificationsController::class, 'unreadCount']);
    Route::post('/read-all', [UserNotificationsController::class, 'markAllRead']);
    Route::post('/{key}/read', [UserNotificationsController::class, 'markRead']);

    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens/{token}', [DeviceTokenController::class, 'destroy'])
        ->where('token', '.*');
});

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:api');
Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:api');

Route::prefix('admin/newsletter')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:newsletter|admin_panel'])->group(function (): void {
    Route::get('/settings', [AdminNewsletterController::class, 'settings']);
    Route::put('/settings', [AdminNewsletterController::class, 'updateSettings']);
    Route::get('/users', [AdminNewsletterController::class, 'users']);
    Route::get('/subscribers', [AdminNewsletterController::class, 'index']);
    Route::post('/recipients', [AdminNewsletterController::class, 'resolveRecipients']);
    Route::post('/send', [AdminNewsletterController::class, 'sendEmail']);
    Route::delete('/subscribers/{newsletterSubscriber}', [AdminNewsletterController::class, 'destroy']);
});
