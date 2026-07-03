<?php

use App\Modules\Selloff\Referral\Http\Controllers\Api\V1\Admin\AdminReferralController;
use App\Modules\Selloff\Referral\Http\Controllers\Api\V1\ReferralController;
use Illuminate\Support\Facades\Route;

Route::get('/referral/program', [ReferralController::class, 'program']);

Route::middleware('auth:sanctum')->prefix('referral')->group(function (): void {
    Route::get('/', [ReferralController::class, 'show']);
    Route::get('/referred-users', [ReferralController::class, 'referredUsers']);
    Route::get('/transactions', [ReferralController::class, 'transactions']);
    Route::get('/redeem-preview', [ReferralController::class, 'redeemPreview']);
    Route::post('/redeem', [ReferralController::class, 'redeem']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:general_settings'])->group(function (): void {
    Route::get('/referral/program', [AdminReferralController::class, 'showProgram']);
    Route::put('/referral/program', [AdminReferralController::class, 'updateProgram']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:admin_panel'])->group(function (): void {
    Route::get('/referrals', [AdminReferralController::class, 'index']);
});
