<?php

use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'admin.pin.login',
    'admin.pin.delete',
    'permission:admin_panel',
])->group(function (): void {
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::get('/roles/create-meta', [RoleController::class, 'createMeta']);
    Route::apiResource('roles', RoleController::class);
});
