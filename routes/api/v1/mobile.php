<?php

use App\Http\Controllers\Api\V1\Mobile\MobileAuthController;
use App\Http\Controllers\Api\V1\Mobile\MobileCatalogController;
use App\Http\Controllers\Api\V1\Mobile\MobileCommerceController;
use App\Http\Controllers\Api\V1\Mobile\MobileCustomFieldController;
use App\Http\Controllers\Api\V1\Mobile\MobileEscrowController;
use App\Http\Controllers\Api\V1\Mobile\MobileLocationController;
use App\Http\Controllers\Api\V1\Mobile\MobileMessagingController;
use App\Http\Controllers\Api\V1\Mobile\MobileVendorController;
use App\Http\Controllers\Api\V1\Mobile\MobileWishlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->middleware('throttle:api')->group(function (): void {
    Route::get('/health', function () {
        return \App\Support\MobileResponse::success([
            'status' => 'ok',
            'auth' => 'sanctum',
            'legacy_jwt' => 'removed',
        ], 200, 'Mobile API ready.');
    });

    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/login', [MobileAuthController::class, 'login']);
        Route::post('/register', [MobileAuthController::class, 'register']);
        Route::post('/forgot-password', [MobileAuthController::class, 'forgotPassword']);
    });

    Route::get('/products', [MobileCatalogController::class, 'paginated']);
    Route::get('/products/{product}', [MobileCatalogController::class, 'show'])->where('product', '[A-Za-z0-9\-]+');
    Route::get('/categories', [MobileCatalogController::class, 'parentCategories']);

    Route::get('/location/countries', [MobileLocationController::class, 'countries']);
    Route::get('/location/states/{countryId}', [MobileLocationController::class, 'states'])->whereNumber('countryId');
    Route::get('/location/cities/{stateId}', [MobileLocationController::class, 'cities'])->whereNumber('stateId');

    Route::get('/custom-fields/category/{categoryId}', [MobileCustomFieldController::class, 'byCategory'])->whereNumber('categoryId');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/profile', [MobileAuthController::class, 'profile']);
        Route::post('/cart/items', [MobileCommerceController::class, 'addToCart']);
        Route::post('/checkout/wallet', [MobileCommerceController::class, 'walletCheckout']);
        Route::get('/orders', [MobileCommerceController::class, 'orders']);
        Route::post('/wishlist/toggle', [MobileWishlistController::class, 'toggle']);

        Route::get('/messages/conversations', [MobileMessagingController::class, 'latestConversations']);
        Route::get('/messages/conversations/{conversationId}', [MobileMessagingController::class, 'messages'])->whereNumber('conversationId');
        Route::get('/messages/unread-count', [MobileMessagingController::class, 'unreadCount']);
        Route::post('/messages/conversations/{conversationId}/read', [MobileMessagingController::class, 'markRead'])->whereNumber('conversationId');
        Route::post('/messages/send', [MobileMessagingController::class, 'sendConversationMessage']);
        Route::post('/messages/new', [MobileMessagingController::class, 'sendNewConversationMessage']);

        Route::get('/escrow/{id}', [MobileEscrowController::class, 'show'])->whereNumber('id');
        Route::post('/escrow/initiate', [MobileEscrowController::class, 'initiate']);

        Route::get('/vendor/shop-opening-status', [MobileVendorController::class, 'shopOpeningStatus']);
        Route::post('/vendor/shop-opening', [MobileVendorController::class, 'startSelling']);
    });
});
