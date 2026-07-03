<?php

use App\Modules\Auth\Http\Controllers\Api\V1\AuthController;
use App\Modules\Auth\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\OAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/verify-email/{token}', [AuthController::class, 'verifyEmail']);
    });
    Route::get('/oauth/{provider}/redirect', [OAuthController::class, 'redirect'])
        ->whereIn('provider', ['facebook', 'google', 'vkontakte']);
    Route::get('/oauth/{provider}/callback', [OAuthController::class, 'callback'])
        ->whereIn('provider', ['facebook', 'google', 'vkontakte']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [MeController::class, 'show']);
        Route::patch('/me', [MeController::class, 'update']);
        Route::put('/password', [MeController::class, 'updatePassword']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerificationEmail']);
        Route::prefix('admin-pin')->group(function (): void {
            Route::get('/status', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminPinController::class, 'status']);
            Route::post('/verify', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminPinController::class, 'verifyLogin']);
        });
    });
});
