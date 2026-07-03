<?php

use App\Http\Controllers\Api\V1\MediaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/media/upload', [MediaController::class, 'upload']);
});
