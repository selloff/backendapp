<?php

use App\Modules\Selloff\Messaging\Http\Controllers\Api\V1\Admin\AdminMessageController;
use App\Modules\Selloff\Messaging\Http\Controllers\Api\V1\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('messages')->group(function (): void {
    Route::get('/latest-conversations', [MessageController::class, 'latestConversations']);
    Route::get('/unread-conversations-count', [MessageController::class, 'unreadCount']);
    Route::get('/{conversationId}', [MessageController::class, 'messages'])->whereNumber('conversationId');
    Route::get('/set-conversation-messages-as-read/{conversationId}', [MessageController::class, 'markRead'])->whereNumber('conversationId');
    Route::post('/send-conversation-message', [MessageController::class, 'sendConversationMessage']);
    Route::post('/send-new-conversation-message', [MessageController::class, 'sendNewConversationMessage']);
});

Route::prefix('admin/messages')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:chat_messages'])->group(function (): void {
    Route::get('/conversations', [AdminMessageController::class, 'conversations']);
    Route::get('/conversations/{conversation}', [AdminMessageController::class, 'show']);
    Route::patch('/conversations/{conversation}', [AdminMessageController::class, 'update']);
    Route::delete('/conversations/{conversation}', [AdminMessageController::class, 'destroy']);
});
