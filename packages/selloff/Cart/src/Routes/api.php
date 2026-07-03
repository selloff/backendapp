<?php

use App\Modules\Selloff\Cart\Http\Controllers\Api\V1\CartController;
use App\Modules\Selloff\Cart\Http\Controllers\Api\V1\GuestCartController;
use Illuminate\Support\Facades\Route;

Route::prefix('guest/cart')->group(function (): void {
    Route::get('/', [GuestCartController::class, 'show']);
    Route::post('/items', [GuestCartController::class, 'addItem']);
    Route::post('/shipping', [GuestCartController::class, 'applyShipping']);
});

Route::middleware('auth:sanctum')->prefix('cart')->group(function (): void {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/items', [CartController::class, 'addItem']);
    Route::post('/merge-guest', [CartController::class, 'mergeGuest']);
    Route::get('/begin-checkout-gtm', [CartController::class, 'beginCheckoutGtm']);
    Route::patch('/items/{cartItem}', [CartController::class, 'updateItem']);
    Route::delete('/items/{cartItem}', [CartController::class, 'removeItem']);
    Route::post('/coupon', [CartController::class, 'applyCoupon']);
    Route::delete('/coupon', [CartController::class, 'removeCoupon']);
    Route::post('/shipping', [CartController::class, 'applyShipping']);
});
