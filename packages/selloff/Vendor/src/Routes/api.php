<?php

use App\Modules\Selloff\Vendor\Http\Controllers\Api\V1\Admin\AdminShopOpeningController;
use App\Modules\Selloff\Vendor\Http\Controllers\Api\V1\VendorController;
use App\Modules\Selloff\Vendor\Http\Controllers\Api\V1\VendorShopOpeningController;
use Illuminate\Support\Facades\Route;

Route::get('/vendors', [VendorController::class, 'index']);
Route::get('/vendors/{slug}/reviews', [VendorController::class, 'reviews']);
Route::get('/vendors/{slug}/feedback/summary', [VendorController::class, 'feedbackSummary']);
Route::get('/vendors/{slug}/feedback', [VendorController::class, 'feedback']);
Route::get('/vendors/{slug}', [VendorController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/shop-opening-request-status', [VendorShopOpeningController::class, 'status']);
    Route::post('/start-selling-verification', [VendorShopOpeningController::class, 'submit']);
    Route::get('/vendors/{slug}/feedback/mine', [VendorController::class, 'myFeedback']);
    Route::post('/vendors/{slug}/feedback', [VendorController::class, 'storeFeedback']);

    Route::prefix('vendors')->group(function (): void {
        Route::get('/me/profile', [VendorController::class, 'me']);
        Route::put('/me/profile', [VendorController::class, 'updateMe']);
    });
});

Route::prefix('admin/shop-opening')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:membership'])->group(function (): void {
    Route::get('/requests', [AdminShopOpeningController::class, 'index']);
    Route::get('/requests/{user}/documents/view', [AdminShopOpeningController::class, 'viewDocument']);
    Route::post('/requests/{user}/approve', [AdminShopOpeningController::class, 'approve']);
    Route::post('/requests/{user}/reject', [AdminShopOpeningController::class, 'reject']);
});
