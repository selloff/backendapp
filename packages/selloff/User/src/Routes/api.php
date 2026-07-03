<?php

use App\Modules\Selloff\User\Http\Controllers\Api\V1\AccountController;
use App\Modules\Selloff\User\Http\Controllers\Api\V1\Admin\AdminAccountDeletionController;
use App\Modules\Selloff\User\Http\Controllers\Api\V1\Admin\AdminLoginActivityController;
use App\Modules\Selloff\User\Http\Controllers\Api\V1\FollowController;
use App\Modules\Selloff\User\Http\Controllers\Api\V1\ShippingAddressController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('profile')->group(function (): void {
    Route::get('/shipping-addresses', [ShippingAddressController::class, 'index']);
    Route::post('/shipping-addresses', [ShippingAddressController::class, 'store']);
    Route::put('/shipping-addresses/{shippingAddress}', [ShippingAddressController::class, 'update']);
    Route::delete('/shipping-addresses/{shippingAddress}', [ShippingAddressController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/account/request-deletion', [AccountController::class, 'requestDeletion']);
    Route::post('/account/delete', [AccountController::class, 'delete']);
    Route::get('/account/following', [FollowController::class, 'following']);
    Route::get('/account/followers', [FollowController::class, 'followers']);
    Route::post('/users/{user}/follow', [FollowController::class, 'store']);
    Route::delete('/users/{user}/follow', [FollowController::class, 'destroy']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:admin_panel'])->group(function (): void {
    Route::get('/login-activities', [AdminLoginActivityController::class, 'index']);
    Route::get('/account-deletion-requests', [AdminAccountDeletionController::class, 'index']);
    Route::post('/account-deletion-requests/{user}/cancel', [AdminAccountDeletionController::class, 'cancel']);
    Route::delete('/account-deletion-requests/{user}', [AdminAccountDeletionController::class, 'destroy']);
});
