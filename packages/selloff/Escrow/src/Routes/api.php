<?php

use App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\EscrowController;
use App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\VendorEscrowController;
use Illuminate\Support\Facades\Route;

Route::get('/escrow/token/{token}', [EscrowController::class, 'showByToken']);
Route::post('/escrow/token/{token}/confirm', [EscrowController::class, 'confirm']);
Route::post('/escrow/token/{token}/confirm-shipped', [EscrowController::class, 'confirmShipped']);
Route::post('/escrow/token/{token}/confirm-delivery', [EscrowController::class, 'confirmDelivery']);
Route::post('/escrow/token/{token}/dispute', [EscrowController::class, 'dispute']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/escrow-transactions', [EscrowController::class, 'index']);
    Route::get('/escrow-transaction/{id}', [EscrowController::class, 'show'])->whereNumber('id');
    Route::post('/initiate-escrow', [EscrowController::class, 'initiate']);
    Route::post('/escrow-transactions/{escrowTransaction}/pay', [EscrowController::class, 'pay']);
    Route::post('/escrow-transactions/{escrowTransaction}/paystack/complete', [EscrowController::class, 'completePaystack']);
});

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/escrow-transactions', [VendorEscrowController::class, 'index']);
    Route::get('/escrow-transactions/{escrowTransaction}', [VendorEscrowController::class, 'show']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:orders'])->group(function (): void {
    Route::get('/escrow/transactions', [\App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\Admin\AdminEscrowController::class, 'index']);
    Route::get('/escrow/transactions/{escrowTransaction}', [\App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\Admin\AdminEscrowController::class, 'show']);
    Route::patch('/escrow/transactions/{escrowTransaction}/status', [\App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\Admin\AdminEscrowController::class, 'updateStatus']);
    Route::patch('/escrow/transactions/{escrowTransaction}/stages', [\App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\Admin\AdminEscrowController::class, 'updateStages']);
    Route::post('/escrow/transactions/{escrowTransaction}/release', [\App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\Admin\AdminEscrowController::class, 'releaseNow']);
    Route::post('/escrow/transactions/{escrowTransaction}/resolve-dispute', [\App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\Admin\AdminEscrowController::class, 'resolveDispute']);
    Route::get('/escrow/transactions/{escrowTransaction}/events', [\App\Modules\Selloff\Escrow\Http\Controllers\Api\V1\Admin\AdminEscrowController::class, 'events']);
});
