<?php

use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'permission:general_settings|product_settings|admin_panel',
    'admin.pin.login',
    'admin.pin.settings',
])->group(function (): void {
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::post('/admin/email/test', [SettingsController::class, 'sendTestEmail']);
});
