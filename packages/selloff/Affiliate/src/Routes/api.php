<?php

use App\Modules\Selloff\Affiliate\Http\Controllers\Api\V1\Admin\AdminAffiliateController;
use App\Modules\Selloff\Affiliate\Http\Controllers\Api\V1\Admin\AdminAffiliateProgramController;
use App\Modules\Selloff\Affiliate\Http\Controllers\Api\V1\AffiliateLinkController;
use App\Modules\Selloff\Affiliate\Http\Controllers\Api\V1\VendorAffiliateController;
use Illuminate\Support\Facades\Route;

Route::get('/affiliate/program', [AffiliateLinkController::class, 'program']);
Route::get('/affiliate/resolve/{shortCode}', [AffiliateLinkController::class, 'resolve']);

Route::middleware('auth:sanctum')->prefix('affiliate')->group(function (): void {
    Route::post('/join', [AffiliateLinkController::class, 'join']);
    Route::get('/links', [AffiliateLinkController::class, 'index']);
    Route::post('/links', [AffiliateLinkController::class, 'store']);
    Route::delete('/links/{link}', [AffiliateLinkController::class, 'destroy']);
    Route::get('/earnings', [AffiliateLinkController::class, 'earnings']);
});

Route::prefix('vendor/affiliate')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/', [VendorAffiliateController::class, 'show']);
    Route::put('/settings', [VendorAffiliateController::class, 'updateSettings']);
    Route::get('/links', [VendorAffiliateController::class, 'links']);
    Route::get('/earnings', [VendorAffiliateController::class, 'earnings']);
});

Route::prefix('admin/affiliate')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:general_settings'])->group(function (): void {
    Route::get('/program', [AdminAffiliateProgramController::class, 'show']);
    Route::put('/program', [AdminAffiliateProgramController::class, 'update']);
});

Route::prefix('admin/affiliate')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:orders'])->group(function (): void {
    Route::get('/links', [AdminAffiliateController::class, 'links']);
    Route::get('/earnings', [AdminAffiliateController::class, 'earnings']);
});
