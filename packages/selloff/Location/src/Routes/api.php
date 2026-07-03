<?php

use App\Modules\Selloff\Location\Http\Controllers\Api\V1\Admin\AdminLocationController;
use App\Modules\Selloff\Location\Http\Controllers\Api\V1\LocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('location')->group(function (): void {
    Route::get('/countries', [LocationController::class, 'countries']);
    Route::get('/browse/states', [LocationController::class, 'browseStates']);
    Route::get('/browse/cities', [LocationController::class, 'browseCities']);
    Route::get('/states/{countryId}', [LocationController::class, 'states'])->whereNumber('countryId');
    Route::get('/cities/{stateId}', [LocationController::class, 'cities'])->whereNumber('stateId');
});

Route::prefix('admin/locations')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:location'])->group(function (): void {
    Route::get('/continents', [AdminLocationController::class, 'continents']);
    Route::get('/countries', [AdminLocationController::class, 'indexCountries']);
    Route::post('/countries/bulk-status', [AdminLocationController::class, 'bulkUpdateCountryStatus']);
    Route::post('/countries', [AdminLocationController::class, 'storeCountry']);
    Route::put('/countries/{country}', [AdminLocationController::class, 'updateCountry']);
    Route::delete('/countries/{country}', [AdminLocationController::class, 'destroyCountry']);
    Route::get('/states', [AdminLocationController::class, 'indexStates']);
    Route::post('/states', [AdminLocationController::class, 'storeState']);
    Route::put('/states/{state}', [AdminLocationController::class, 'updateState']);
    Route::delete('/states/{state}', [AdminLocationController::class, 'destroyState']);
    Route::get('/cities', [AdminLocationController::class, 'indexCities']);
    Route::post('/cities', [AdminLocationController::class, 'storeCity']);
    Route::put('/cities/{city}', [AdminLocationController::class, 'updateCity']);
    Route::delete('/cities/{city}', [AdminLocationController::class, 'destroyCity']);
});
