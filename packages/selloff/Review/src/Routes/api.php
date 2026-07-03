<?php

use App\Modules\Selloff\Review\Http\Controllers\Api\V1\AccountReviewController;
use App\Modules\Selloff\Review\Http\Controllers\Api\V1\Admin\AdminCommentController;
use App\Modules\Selloff\Review\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Modules\Selloff\Review\Http\Controllers\Api\V1\FeedbackAbuseReportController;
use App\Modules\Selloff\Review\Http\Controllers\Api\V1\ProductAbuseReportController;
use App\Modules\Selloff\Review\Http\Controllers\Api\V1\ProductCommentController;
use App\Modules\Selloff\Review\Http\Controllers\Api\V1\ProductReviewController;
use App\Modules\Selloff\Review\Http\Controllers\Api\V1\VendorCommentController;
use App\Modules\Selloff\Review\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/products/{product}/reviews', [ProductReviewController::class, 'index']);
Route::get('/products/{product}/comments', [ProductCommentController::class, 'index']);
Route::get('/wishlist/guest-preview', [WishlistController::class, 'guestPreview']);
Route::get('/preferences/marketplace', [\App\Modules\Selloff\User\Http\Controllers\Api\V1\PreferencesController::class, 'marketplace']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/products/{product}/reviews', [ProductReviewController::class, 'store']);
    Route::post('/products/{product}/comments', [ProductCommentController::class, 'store']);
    Route::post('/products/{product}/report', [ProductAbuseReportController::class, 'store']);
    Route::post('/feedbacks/{feedback}/report', [FeedbackAbuseReportController::class, 'store'])->whereNumber('feedback');

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/merge-guest', [WishlistController::class, 'mergeGuest']);
    Route::post('/wishlist/{product}', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy']);

    Route::get('/account/reviews', [AccountReviewController::class, 'index']);
});

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/reviews', [\App\Modules\Selloff\Review\Http\Controllers\Api\V1\VendorReviewController::class, 'index']);
    Route::get('/comments', [VendorCommentController::class, 'index']);
    Route::patch('/comments/{comment}', [VendorCommentController::class, 'update']);
});

Route::prefix('admin/comments')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:comments'])->group(function (): void {
    Route::get('/', [AdminCommentController::class, 'index']);
    Route::post('/bulk', [AdminCommentController::class, 'bulk']);
    Route::patch('/{comment}', [AdminCommentController::class, 'update']);
    Route::delete('/{comment}', [AdminCommentController::class, 'destroy']);
});

Route::prefix('admin/reviews')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:reviews'])->group(function (): void {
    Route::get('/', [AdminReviewController::class, 'index']);
    Route::post('/bulk-delete', [AdminReviewController::class, 'bulkDestroy']);
    Route::patch('/{review}', [AdminReviewController::class, 'update']);
    Route::delete('/{review}', [AdminReviewController::class, 'destroy']);
});
