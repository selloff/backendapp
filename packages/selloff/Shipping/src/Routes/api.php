<?php

use App\Modules\Selloff\Shipping\Http\Controllers\Api\V1\ShippingController;
use App\Modules\Selloff\Shipping\Http\Controllers\Api\V1\VendorShippingController;
use Illuminate\Support\Facades\Route;

Route::get('/shipping/quote', [ShippingController::class, 'quote']);

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/shipping/zones', [VendorShippingController::class, 'index']);
    Route::post('/shipping/zones', [VendorShippingController::class, 'storeZone']);
    Route::get('/shipping/zones/{shippingZone}', [VendorShippingController::class, 'showZone']);
    Route::put('/shipping/zones/{shippingZone}', [VendorShippingController::class, 'updateZone']);
    Route::delete('/shipping/zones/{shippingZone}', [VendorShippingController::class, 'destroyZone']);
    Route::post('/shipping/zones/{shippingZone}/methods', [VendorShippingController::class, 'storeMethod']);
    Route::put('/shipping/zones/{shippingZone}/methods/{shippingMethod}', [VendorShippingController::class, 'updateMethod']);
    Route::delete('/shipping/zones/{shippingZone}/methods/{shippingMethod}', [VendorShippingController::class, 'destroyMethod']);

    Route::post('/shipping/delivery-times', [VendorShippingController::class, 'storeDeliveryTime']);
    Route::put('/shipping/delivery-times/{deliveryTimeOption}', [VendorShippingController::class, 'updateDeliveryTime']);
    Route::delete('/shipping/delivery-times/{deliveryTimeOption}', [VendorShippingController::class, 'destroyDeliveryTime']);
});
