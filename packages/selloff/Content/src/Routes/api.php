<?php

use App\Modules\Selloff\Content\Http\Controllers\Api\V1\AdSpaceController;
use App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin\AdminAdSpaceController;
use App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin\AdminBlogCommentController;
use App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin\AdminBlogController;
use App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin\AdminBlogImageController;
use App\Modules\Selloff\Content\Http\Controllers\Api\V1\Admin\AdminHomepageController;
use App\Modules\Selloff\Content\Http\Controllers\Api\V1\BlogController;
use App\Modules\Selloff\Content\Http\Controllers\Api\V1\HomepageController;
use App\Modules\Selloff\Content\Http\Controllers\Api\V1\PageController;
use App\Modules\Selloff\Content\Http\Controllers\RssFeedController;
use Illuminate\Support\Facades\Route;

Route::get('/blog/posts', [BlogController::class, 'index']);
Route::get('/blog/categories', [BlogController::class, 'categories']);
Route::get('/blog/posts/{slug}', [BlogController::class, 'show']);
Route::get('/blog/posts/{slug}/comments', [BlogController::class, 'comments']);
Route::post('/blog/posts/{slug}/comments', [BlogController::class, 'storeComment'])->middleware('auth:sanctum');
Route::get('/blog/tags/{tagSlug}', [BlogController::class, 'tag']);
Route::get('/homepage', [HomepageController::class, 'index']);
Route::get('/homepage/sliders', [HomepageController::class, 'sliders']);
Route::get('/homepage/banners', [HomepageController::class, 'banners']);
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/ad-spaces/{key}', [AdSpaceController::class, 'show']);
Route::get('/rss/directory', [RssFeedController::class, 'directory']);

Route::prefix('admin/cms')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:homepage_manager|slider'])->group(function (): void {
    Route::get('/homepage/sliders', [AdminHomepageController::class, 'sliders']);
    Route::post('/homepage/sliders', [AdminHomepageController::class, 'storeSlider']);
    Route::put('/homepage/sliders/{slider}', [AdminHomepageController::class, 'updateSlider']);
    Route::delete('/homepage/sliders/{slider}', [AdminHomepageController::class, 'destroySlider']);
    Route::get('/homepage/banners', [AdminHomepageController::class, 'banners']);
    Route::post('/homepage/banners', [AdminHomepageController::class, 'storeBanner']);
    Route::put('/homepage/banners/{homepageBanner}', [AdminHomepageController::class, 'updateBanner']);
    Route::delete('/homepage/banners/{homepageBanner}', [AdminHomepageController::class, 'destroyBanner']);
    Route::get('/homepage/category-layout', [AdminHomepageController::class, 'categoryLayout']);
    Route::put('/homepage/featured-categories', [AdminHomepageController::class, 'syncFeaturedCategories']);
    Route::put('/homepage/index-categories', [AdminHomepageController::class, 'syncIndexCategories']);
});

Route::prefix('admin/cms')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:blog|pages'])->group(function (): void {
    Route::get('/blog/posts', [AdminBlogController::class, 'indexPosts']);
    Route::get('/blog/posts/{blogPost}', [AdminBlogController::class, 'showPost']);
    Route::post('/blog/posts', [AdminBlogController::class, 'storePost']);
    Route::put('/blog/posts/{blogPost}', [AdminBlogController::class, 'updatePost']);
    Route::delete('/blog/posts/{blogPost}', [AdminBlogController::class, 'destroyPost']);
    Route::get('/blog/images', [AdminBlogImageController::class, 'index']);
    Route::post('/blog/images', [AdminBlogImageController::class, 'store']);
    Route::delete('/blog/images/{blogImage}', [AdminBlogImageController::class, 'destroy']);
    Route::get('/blog/categories', [AdminBlogController::class, 'indexCategories']);
    Route::post('/blog/categories', [AdminBlogController::class, 'storeCategory']);
    Route::put('/blog/categories/{blogCategory}', [AdminBlogController::class, 'updateCategory']);
    Route::delete('/blog/categories/{blogCategory}', [AdminBlogController::class, 'destroyCategory']);
    Route::get('/pages', [AdminBlogController::class, 'indexPages']);
    Route::post('/pages', [AdminBlogController::class, 'storePage']);
    Route::put('/pages/{page}', [AdminBlogController::class, 'updatePage']);
    Route::delete('/pages/{page}', [AdminBlogController::class, 'destroyPage']);
});

Route::prefix('admin/cms')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:ad_spaces'])->group(function (): void {
    Route::get('/ad-spaces', [AdminAdSpaceController::class, 'index']);
    Route::get('/ad-spaces/by-key/{key}', [AdminAdSpaceController::class, 'showByKey']);
    Route::get('/ad-spaces/google-adsense', [AdminAdSpaceController::class, 'adsense']);
    Route::put('/ad-spaces/google-adsense', [AdminAdSpaceController::class, 'updateAdsense']);
    Route::post('/ad-spaces', [AdminAdSpaceController::class, 'store']);
    Route::put('/ad-spaces/{adSpace}', [AdminAdSpaceController::class, 'update']);
    Route::delete('/ad-spaces/{adSpace}', [AdminAdSpaceController::class, 'destroy']);
});

Route::prefix('admin/cms')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:comments'])->group(function (): void {
    Route::get('/blog/comments', [AdminBlogCommentController::class, 'index']);
    Route::post('/blog/comments/bulk', [AdminBlogCommentController::class, 'bulk']);
    Route::patch('/blog/comments/{blogComment}', [AdminBlogCommentController::class, 'update']);
    Route::delete('/blog/comments/{blogComment}', [AdminBlogCommentController::class, 'destroy']);
});
