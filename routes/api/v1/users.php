<?php

use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'admin.pin.login',
    'admin.pin.delete',
    'permission:admin_panel',
])->group(function (): void {
    Route::get('/users/index-meta', [UserController::class, 'indexMeta']);
    Route::get('/users/{user}/summary', [UserController::class, 'summary']);
    Route::post('/users/{user}/confirm-email', [UserController::class, 'confirmEmail']);
    Route::post('/users/{user}/toggle-ban', [UserController::class, 'toggleBan']);
    Route::post('/users/{user}/toggle-affiliate', [UserController::class, 'toggleAffiliate']);
    Route::post('/users/{user}/change-role', [UserController::class, 'changeRole']);
    Route::post('/users/{user}/assign-membership-plan', [UserController::class, 'assignMembershipPlan']);
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate']);
    Route::apiResource('users', UserController::class);
});
