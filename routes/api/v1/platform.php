<?php

use App\Actions\Health\CheckHealthAction;
use App\Modules\Auth\Http\Controllers\Api\V1\PlatformBrandController;
use App\Support\ApiResponse;

Route::get('/health', function (CheckHealthAction $checkHealth) {
    $result = $checkHealth->execute();
    $status = $result['status'] === 'ok' ? 200 : 503;

    return ApiResponse::success($result, $status);
});

Route::prefix('public')->group(function (): void {
    Route::get('/platform-brand', [PlatformBrandController::class, 'show']);
});
