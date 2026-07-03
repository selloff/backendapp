<?php

use App\Http\Controllers\Api\V1\Mobile\MobileAuthController;
use App\Http\Controllers\Api\V1\Mobile\MobileCatalogController;
use App\Http\Controllers\Api\V1\Mobile\MobileCustomFieldController;
use App\Http\Controllers\Api\V1\Mobile\MobileEscrowController;
use App\Http\Controllers\Api\V1\Mobile\MobileLegacyProductController;
use App\Http\Controllers\Api\V1\Mobile\MobileLocationController;
use App\Http\Controllers\Api\V1\Mobile\MobileMessagingController;
use App\Http\Controllers\Api\V1\Mobile\MobileVendorController;
use App\Http\Controllers\Api\V1\Mobile\MobileSandboxController;
use App\Http\Controllers\Api\V1\Mobile\MobileWishlistController;
use App\Support\MobileResponse;
use Illuminate\Support\Facades\Route;

/*
| Legacy CI4 mobile JWT paths — Sanctum-backed compatibility shims.
| Prefer /api/v1/mobile/* or /api/v1/auth/* for new clients.
*/

Route::middleware('throttle:auth')->group(function (): void {
    Route::post('login', [MobileAuthController::class, 'login']);
    Route::post('register', [MobileAuthController::class, 'register']);
    Route::post('forgot-password', [MobileAuthController::class, 'forgotPassword']);
});

Route::middleware('throttle:api')->group(function (): void {
    Route::get('generate-referral-code', fn () => MobileResponse::success(['code' => strtoupper(substr(uniqid(), -8))]));

    // Do not register GET /products — core Catalog ProductController owns that path for SPA/web API.
    // Numeric product detail for legacy mobile clients: GET /api/v1/mobile/products/{id}
    Route::get('promoted-products', [MobileLegacyProductController::class, 'promoted']);
    Route::get('products/paginated', [MobileCatalogController::class, 'paginated']);
    Route::get('products/paginated-by-category-slug', [MobileCatalogController::class, 'paginatedByCategorySlug']);
    Route::get('products/product-images/{productId}', [MobileLegacyProductController::class, 'productImages'])->whereNumber('productId');
    Route::get('products/paginated-by-declutter', [MobileCatalogController::class, 'paginatedDeclutter']);
    Route::get('products/paginated-by-freebies', [MobileCatalogController::class, 'paginatedFreebies']);
    Route::get('products/paginated-by-latest-listings', [MobileLegacyProductController::class, 'paginated']);
    Route::get('products/paginated-by-promoted-listings', [MobileLegacyProductController::class, 'promoted']);
    Route::get('products/related/{productId}/{limit}', [MobileLegacyProductController::class, 'related'])->whereNumber('productId')->whereNumber('limit');
    Route::get('products/paginated-listing-search/{query}', [MobileLegacyProductController::class, 'listingSearch']);
    Route::get('products/category-slug-limited/{slug}/{limit}', [MobileLegacyProductController::class, 'categorySlugLimited']);
    Route::get('products/latest-listing-limited/{limit}', [MobileLegacyProductController::class, 'latestLimited'])->whereNumber('limit');
    Route::get('products/promoted-listing-limited/{limit}', [MobileLegacyProductController::class, 'promotedLimited'])->whereNumber('limit');
    Route::get('products/listing-custom-fields/{categoryId}', [MobileCustomFieldController::class, 'listingFields'])->whereNumber('categoryId');

    Route::get('parent-categories', [MobileCatalogController::class, 'parentCategories']);
    Route::get('categories-json', [MobileCatalogController::class, 'parentCategories']);

    Route::get('customfields/custom-fields-by-category-all-data-new/{categoryId}', [MobileCustomFieldController::class, 'byCategory'])->whereNumber('categoryId');

    Route::get('users', fn () => MobileResponse::error('Use admin users API.', 403));
    // Do not register GET /vendors — Vendor VendorController owns that path for SPA/web API.

    Route::get('location/countries', [MobileLocationController::class, 'countries']);
    Route::get('location/states/{countryId}', [MobileLocationController::class, 'states'])->whereNumber('countryId');
    Route::get('location/cities/{stateId}', [MobileLocationController::class, 'cities'])->whereNumber('stateId');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('update-product-category', [MobileSandboxController::class, 'updateProductCategory']);

    Route::get('messages/latest-conversations', [MobileMessagingController::class, 'latestConversations']);
    Route::get('users/profile', [MobileAuthController::class, 'profile']);
    Route::post('users/profile', [MobileAuthController::class, 'updateProfile']);
    Route::post('users/delete-account', [MobileAuthController::class, 'deleteAccount']);
    Route::post('app-user-feedback', fn () => MobileResponse::success([], 201, 'Feedback received.'));

    Route::post('products/add-remove-wishlist', [MobileWishlistController::class, 'toggle']);
    Route::post('products/follow-seller', [MobileLegacyProductController::class, 'followSeller']);
    Route::post('products/report-seller', [MobileLegacyProductController::class, 'reportSeller']);
    Route::post('products/report-user', [MobileLegacyProductController::class, 'reportUser']);
    Route::post('products/report-item', [MobileLegacyProductController::class, 'reportItem']);
    Route::get('products/paginated-by-fovourite-listings', [MobileCatalogController::class, 'paginatedFavourites']);

    Route::get('escrow-transaction/{id}', [MobileEscrowController::class, 'show'])->whereNumber('id');
    Route::post('initiate-escrow', [MobileEscrowController::class, 'initiate']);

    Route::get('shop-opening-request-status', [MobileVendorController::class, 'shopOpeningStatus']);
    Route::post('start-selling-verification', [MobileVendorController::class, 'startSelling']);
    Route::post('post-listing-item', [MobileVendorController::class, 'postListingItem']);

    Route::get('messages/{conversationId}', [MobileMessagingController::class, 'messages'])->whereNumber('conversationId');
    Route::get('messages/unread-conversations/{userId}', [MobileMessagingController::class, 'latestConversations'])->whereNumber('userId');
    Route::get('messages/read-conversations/{userId}', [MobileMessagingController::class, 'latestConversations'])->whereNumber('userId');
    Route::get('messages/conversations/{userId}', [MobileMessagingController::class, 'latestConversations'])->whereNumber('userId');
    Route::get('messages/unread-conversations-count', [MobileMessagingController::class, 'unreadCount']);
    Route::get('messages/set-conversation-messages-as-read/{conversationId}', [MobileMessagingController::class, 'markRead'])->whereNumber('conversationId');
    Route::post('messages/send-conversation-message', [MobileMessagingController::class, 'sendConversationMessage']);
    Route::post('messages/send-new-conversation-message', [MobileMessagingController::class, 'sendNewConversationMessage']);
});
