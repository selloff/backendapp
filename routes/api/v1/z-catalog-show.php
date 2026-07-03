<?php

use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\ProductController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\ProductContactController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\ProductViewGtmController;
use Illuminate\Support\Facades\Route;

/*
| SPA product detail — registered after mobile-legacy shims so paths like
| /products/paginated are not captured as {product} slugs.
*/
Route::get('/products/{product}', [ProductController::class, 'show'])->where('product', '[A-Za-z0-9\-]+');
Route::get('/products/{product}/shipping-estimate', [ProductController::class, 'shippingEstimate'])->where('product', '[A-Za-z0-9\-]+');
Route::get('/products/{product}/view-gtm', [ProductViewGtmController::class, 'show'])->where('product', '[A-Za-z0-9\-]+');
Route::post('/products/{product}/view-contact', [ProductContactController::class, 'viewContact'])->where('product', '[A-Za-z0-9\-]+');
Route::post('/products/{product}/click-to-call', [ProductContactController::class, 'clickToCall'])->where('product', '[A-Za-z0-9\-]+');
