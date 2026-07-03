<?php

use App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\AccountCouponController;
use App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorCouponController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('account')->group(function (): void {
    Route::get('/coupons', [AccountCouponController::class, 'index']);
    Route::get('/coupons/available', [AccountCouponController::class, 'available']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:products'])->group(function (): void {
    Route::get('/featured-pricing', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\Admin\AdminFeaturedPricingController::class, 'show']);
    Route::put('/featured-pricing', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\Admin\AdminFeaturedPricingController::class, 'update']);
    Route::get('/promotion-transactions', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\Admin\AdminPromotionTransactionController::class, 'index']);
    Route::get('/promotion-transactions/{promotionTransaction}/invoice', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\Admin\AdminPromotionTransactionController::class, 'invoice']);
    Route::post('/promotion-transactions/{promotionTransaction}/approve', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\Admin\AdminPromotionTransactionController::class, 'approve']);
});

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/top-ad-pricing', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorTopAdController::class, 'pricing']);
    Route::post('/products/{product}/purchase-top-ad', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorTopAdController::class, 'purchase']);
    Route::post('/top-ad-transactions/{promotionTransaction}/paystack/complete', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorTopAdController::class, 'completePaystack']);
    Route::post('/top-ad-transactions/{promotionTransaction}/resume-payment', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorTopAdController::class, 'resumePayment']);
    Route::get('/promotion-pricing', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorProductPromotionController::class, 'pricing']);
    Route::post('/products/{product}/promote', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorProductPromotionController::class, 'store']);
    Route::post('/promotion-transactions/{promotionTransaction}/paystack/complete', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorProductPromotionController::class, 'completePaystack']);
    Route::get('/promotion-transactions/{promotionTransaction}/invoice', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorPromotionTransactionController::class, 'invoice']);
    Route::post('/promotion-transactions/{promotionTransaction}/resume-payment', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorPromotionTransactionController::class, 'resumePayment']);
    Route::get('/promotion-transactions', [\App\Modules\Selloff\Promotion\Http\Controllers\Api\V1\VendorPromotionTransactionController::class, 'index']);
    Route::get('/coupons', [VendorCouponController::class, 'index']);
    Route::post('/coupons', [VendorCouponController::class, 'store']);
    Route::put('/coupons/{coupon}', [VendorCouponController::class, 'update']);
    Route::delete('/coupons/{coupon}', [VendorCouponController::class, 'destroy']);
});
