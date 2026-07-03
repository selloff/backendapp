<?php

use App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin\AdminContactController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin\AdminFeedbackController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin\AdminFeedbackDisputeController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin\AdminKnowledgeBaseController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\Admin\AdminSupportController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\AccountVendorFeedbackController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\ContactController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\KnowledgeBaseController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\SupportTicketController;
use App\Modules\Selloff\Support\Http\Controllers\Api\V1\VendorFeedbackController;
use Illuminate\Support\Facades\Route;

Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:api');

Route::prefix('support/kb')->group(function (): void {
    Route::get('/', [KnowledgeBaseController::class, 'index']);
    Route::get('/articles/{slug}', [KnowledgeBaseController::class, 'show']);
});

Route::middleware('auth:sanctum')->prefix('support')->group(function (): void {
    Route::get('/tickets', [SupportTicketController::class, 'index']);
    Route::post('/tickets', [SupportTicketController::class, 'store']);
    Route::get('/tickets/{supportTicket}', [SupportTicketController::class, 'show']);
    Route::post('/tickets/{supportTicket}/reply', [SupportTicketController::class, 'reply']);
    Route::patch('/tickets/{supportTicket}/close', [SupportTicketController::class, 'close']);
});

Route::middleware('auth:sanctum')->prefix('account')->group(function (): void {
    Route::get('/feedback', [AccountVendorFeedbackController::class, 'index']);
    Route::post('/feedback/{feedback}/reply', [AccountVendorFeedbackController::class, 'reply']);
});

Route::prefix('admin/support')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:help_center'])->group(function (): void {
    Route::get('/tickets', [AdminSupportController::class, 'tickets']);
    Route::get('/tickets/{supportTicket}', [AdminSupportController::class, 'show']);
    Route::post('/tickets/{supportTicket}/reply', [AdminSupportController::class, 'reply']);
    Route::patch('/tickets/{supportTicket}', [AdminSupportController::class, 'update']);
    Route::delete('/tickets/{supportTicket}', [AdminSupportController::class, 'destroy']);

    Route::get('/kb/categories', [AdminKnowledgeBaseController::class, 'categories']);
    Route::post('/kb/categories', [AdminKnowledgeBaseController::class, 'storeCategory']);
    Route::put('/kb/categories/{knowledgeBaseCategory}', [AdminKnowledgeBaseController::class, 'updateCategory']);
    Route::delete('/kb/categories/{knowledgeBaseCategory}', [AdminKnowledgeBaseController::class, 'destroyCategory']);
    Route::get('/kb/articles', [AdminKnowledgeBaseController::class, 'articles']);
    Route::post('/kb/articles', [AdminKnowledgeBaseController::class, 'storeArticle']);
    Route::put('/kb/articles/{knowledgeBaseArticle}', [AdminKnowledgeBaseController::class, 'updateArticle']);
    Route::delete('/kb/articles/{knowledgeBaseArticle}', [AdminKnowledgeBaseController::class, 'destroyArticle']);
});

Route::prefix('admin/contact')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:contact_messages'])->group(function (): void {
    Route::get('/messages', [AdminContactController::class, 'index']);
    Route::get('/messages/{contactMessage}', [AdminContactController::class, 'show']);
    Route::post('/messages/{contactMessage}/reply', [AdminContactController::class, 'reply']);
    Route::patch('/messages/{contactMessage}', [AdminContactController::class, 'update']);
    Route::delete('/messages/{contactMessage}', [AdminContactController::class, 'destroy']);
});

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/feedback', [VendorFeedbackController::class, 'index']);
    Route::patch('/feedback/{feedback}', [VendorFeedbackController::class, 'update']);
    Route::post('/feedback/{feedback}/reply', [VendorFeedbackController::class, 'reply']);
    Route::post('/feedback/{feedback}/dispute', [VendorFeedbackController::class, 'dispute']);
});

Route::prefix('admin/feedback')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:help_center'])->group(function (): void {
    Route::get('/', [AdminFeedbackController::class, 'index']);
    Route::patch('/{feedback}', [AdminFeedbackController::class, 'update']);
});

Route::prefix('admin/feedback-disputes')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:help_center'])->group(function (): void {
    Route::get('/', [AdminFeedbackDisputeController::class, 'index']);
    Route::patch('/{feedbackDispute}', [AdminFeedbackDisputeController::class, 'update']);
});
