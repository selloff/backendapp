<?php

use App\Modules\Selloff\Payout\Http\Controllers\Api\V1\Admin\AdminEarningsController;
use App\Modules\Selloff\Payout\Http\Controllers\Api\V1\Admin\AdminPayoutController;
use App\Modules\Selloff\Payout\Http\Controllers\Api\V1\VendorEarningsController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/earnings', [VendorEarningsController::class, 'summary']);
    Route::get('/payouts', [VendorEarningsController::class, 'payouts']);
    Route::post('/payouts', [VendorEarningsController::class, 'storePayout']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:payouts'])->group(function (): void {
    Route::get('/payouts', [AdminPayoutController::class, 'index']);
    Route::post('/payouts', [AdminPayoutController::class, 'store']);
    Route::delete('/payouts/{payoutRequest}', [AdminPayoutController::class, 'destroy']);
    Route::post('/payouts/{payoutRequest}/approve', [AdminPayoutController::class, 'approve']);
    Route::post('/payouts/{payoutRequest}/reject', [AdminPayoutController::class, 'reject']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:earnings'])->group(function (): void {
    Route::prefix('earnings')->group(function (): void {
        Route::get('/', [AdminEarningsController::class, 'index']);
        Route::get('/summary', [AdminEarningsController::class, 'summary']);
        Route::delete('/{vendorEarning}', [AdminEarningsController::class, 'destroy']);
        Route::patch('/seller-balances/{seller}', [AdminEarningsController::class, 'updateSellerBalance']);
        Route::get('/seller-balances', [AdminEarningsController::class, 'sellerBalances']);
        Route::get('/payout-settings', [AdminEarningsController::class, 'payoutSettings']);
        Route::put('/payout-settings', [AdminEarningsController::class, 'updatePayoutSettings']);
    });
});
