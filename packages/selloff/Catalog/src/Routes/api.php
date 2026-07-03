<?php

use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\AiWriterController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminBrandController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminCategoryBulkController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminCustomFieldBulkController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminCustomFieldController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminTagController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\BrandController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\CategoryController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\CategoryProductFilterController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\CustomFieldController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\ProductController;
use App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\VendorBulkProductController;
use Illuminate\Support\Facades\Route;

Route::get('/products/recommended', [ProductController::class, 'recommended']);
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products/listing-impressions', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\ListingImpressionController::class, 'store'])
    ->middleware('throttle:api');
Route::get('/products/{product}/related', [ProductController::class, 'related'])->where('product', '[A-Za-z0-9\-]+');
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/categories/{category}/product-filters', [CategoryProductFilterController::class, 'index']);
Route::get('/categories/{category}/product-filters/{filterKey}/options', [CategoryProductFilterController::class, 'options']);
Route::get('/categories/{category}/children', [CategoryController::class, 'children']);
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/customfields/custom-fields-by-category-all-data-new/{categoryId}', [CustomFieldController::class, 'byCategory'])->whereNumber('categoryId');
Route::get('/products/listing-custom-fields/{categoryId}', [CustomFieldController::class, 'listingFields'])->whereNumber('categoryId');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/ai-writer/generate', [AiWriterController::class, 'generate']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:categories|products|brands|tags|custom_fields'])->group(function (): void {
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::get('/categories/settings', [AdminCategoryController::class, 'settings']);
    Route::put('/categories/settings', [AdminCategoryController::class, 'updateSettings']);
    Route::put('/categories/reorder', [AdminCategoryController::class, 'reorder']);
    Route::post('/categories/rebuild-paths', [AdminCategoryController::class, 'rebuildPaths']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::post('/categories/bulk', [AdminCategoryBulkController::class, 'store']);
    Route::get('/products/export', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'export']);
    Route::post('/products/bulk', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'bulk']);
    Route::get('/products', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'index']);
    Route::get('/products/{product}', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'show']);
    Route::put('/products/{product}', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'update']);
    Route::post('/products/{product}/approve', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'approve']);
    Route::post('/products/{product}/reject', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'reject']);
    Route::post('/products/{product}/featured', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'addFeatured']);
    Route::delete('/products/{product}/featured', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'removeFeatured']);
    Route::post('/products/{product}/special-offer', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'addSpecialOffer']);
    Route::delete('/products/{product}/special-offer', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\Admin\AdminProductController::class, 'removeSpecialOffer']);
    Route::get('/brands/settings', [AdminBrandController::class, 'settings']);
    Route::put('/brands/settings', [AdminBrandController::class, 'updateSettings']);
    Route::get('/brands', [AdminBrandController::class, 'index']);
    Route::post('/brands', [AdminBrandController::class, 'store']);
    Route::get('/brands/{brand}', [AdminBrandController::class, 'show']);
    Route::put('/brands/{brand}', [AdminBrandController::class, 'update']);
    Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy']);

    Route::prefix('catalog')->group(function (): void {
        Route::post('/custom-fields/bulk', [AdminCustomFieldBulkController::class, 'store']);
        Route::get('/custom-fields', [AdminCustomFieldController::class, 'index']);
        Route::post('/custom-fields', [AdminCustomFieldController::class, 'store']);
        Route::get('/custom-fields/{customField}', [AdminCustomFieldController::class, 'show']);
        Route::put('/custom-fields/{customField}', [AdminCustomFieldController::class, 'update']);
        Route::delete('/custom-fields/{customField}', [AdminCustomFieldController::class, 'destroy']);
        Route::post('/custom-fields/{customField}/categories', [AdminCustomFieldController::class, 'syncCategories']);
        Route::post('/custom-fields/{customField}/categories/attach', [AdminCustomFieldController::class, 'attachCategory']);
        Route::delete('/custom-fields/{customField}/categories/{category}', [AdminCustomFieldController::class, 'detachCategory']);
        Route::post('/custom-fields/{customField}/toggle-product-filter', [AdminCustomFieldController::class, 'toggleProductFilter']);
        Route::post('/custom-fields/{customField}/options', [AdminCustomFieldController::class, 'storeOption']);
        Route::put('/custom-fields/{customField}/options/{option}', [AdminCustomFieldController::class, 'updateOption']);
        Route::delete('/custom-fields/{customField}/options/{option}', [AdminCustomFieldController::class, 'destroyOption']);
    });

    Route::get('/tags', [AdminTagController::class, 'index']);
    Route::post('/tags', [AdminTagController::class, 'store']);
    Route::put('/tags/{tag}', [AdminTagController::class, 'update']);
    Route::delete('/tags/{tag}', [AdminTagController::class, 'destroy']);
});

Route::prefix('vendor')->middleware(['auth:sanctum', 'permission:vendor'])->group(function (): void {
    Route::get('/products', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\VendorProductController::class, 'index']);
    Route::post('/products/{product}/duplicate', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\VendorProductController::class, 'duplicate']);
    Route::post('/products/{product}/mark-sold', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\VendorProductController::class, 'markSold']);
    Route::post('/products/{product}/affiliate/toggle', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\VendorProductController::class, 'toggleAffiliate']);
    Route::get('/products/{product}', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\VendorProductController::class, 'show']);
    Route::get('/listing-performance', [\App\Modules\Selloff\Catalog\Http\Controllers\Api\V1\VendorListingPerformanceController::class, 'show']);
    Route::post('/products/bulk', [VendorBulkProductController::class, 'store']);
});
