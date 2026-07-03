<?php

use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminAnalyticsController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminNotificationsController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminCurrencyController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminDatabaseBackupController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminListingPerformanceController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminReportsController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminPlatformController;
use App\Modules\Selloff\Admin\Services\AdminReportService;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminRouteController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminSeoController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminThemeController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\CurrencyController;
use App\Modules\Selloff\Admin\Http\Controllers\Api\V1\LanguageController;
use Illuminate\Support\Facades\Route;

Route::get('/currencies', [CurrencyController::class, 'index']);
Route::get('/languages', [LanguageController::class, 'index']);

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:admin_panel'])->group(function (): void {
    Route::get('/dashboard', [AdminDashboardController::class, 'show']);
    Route::get('/analytics', [AdminAnalyticsController::class, 'show']);
    Route::get('/notifications', [AdminNotificationsController::class, 'index']);
    Route::get('/notifications/unread-count', [AdminNotificationsController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [AdminNotificationsController::class, 'markAllRead']);
    Route::post('/notifications/{key}/read', [AdminNotificationsController::class, 'markRead'])
        ->where('key', '.+');
    Route::get('/listing-performance', [AdminListingPerformanceController::class, 'show']);
    Route::get('/reports/{type}/export', [AdminReportsController::class, 'export'])
        ->whereIn('type', AdminReportService::TYPES);
    Route::get('/reports/{type}', [AdminReportsController::class, 'show'])
        ->whereIn('type', AdminReportService::TYPES);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:payment_settings'])->group(function (): void {
    Route::get('/currencies', [AdminCurrencyController::class, 'index']);
    Route::post('/currencies', [AdminCurrencyController::class, 'store']);
    Route::get('/currencies/{currency}', [AdminCurrencyController::class, 'show']);
    Route::put('/currencies/{currency}', [AdminCurrencyController::class, 'update']);
    Route::delete('/currencies/{currency}', [AdminCurrencyController::class, 'destroy']);
    Route::post('/currencies/refresh-rates', [AdminCurrencyController::class, 'refreshRates']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:cache_system'])->group(function (): void {
    Route::get('/platform/cache', [AdminPlatformController::class, 'cacheSettings']);
    Route::put('/platform/cache', [AdminPlatformController::class, 'updateCacheSettings']);
    Route::post('/platform/cache/reset', [AdminPlatformController::class, 'resetCache']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:preferences'])->group(function (): void {
    Route::get('/platform/preferences', [AdminPlatformController::class, 'preferences']);
    Route::put('/platform/preferences', [AdminPlatformController::class, 'updatePreferences']);
    Route::put('/platform/ai-writer', [AdminPlatformController::class, 'updateAiWriterSettings']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:preferences'])->group(function (): void {
    Route::put('/platform/storage', [AdminPlatformController::class, 'updateStorageSettings']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:abuse_reports'])->group(function (): void {
    Route::get('/abuse-reports', [AdminPlatformController::class, 'abuseReports']);
    Route::patch('/abuse-reports/{abuseReport}', [AdminPlatformController::class, 'updateAbuseReport'])->whereNumber('abuseReport');
    Route::delete('/abuse-reports/{abuseReport}', [AdminPlatformController::class, 'destroyAbuseReport'])->whereNumber('abuseReport');
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:seo_tools'])->group(function (): void {
    Route::get('/seo', [AdminSeoController::class, 'show']);
    Route::put('/seo', [AdminSeoController::class, 'update']);
    Route::get('/seo/sitemaps', [AdminSeoController::class, 'sitemaps']);
    Route::post('/seo/sitemap/generate', [AdminSeoController::class, 'generateSitemap']);
    Route::delete('/seo/sitemap', [AdminSeoController::class, 'destroySitemap']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:theme'])->group(function (): void {
    Route::get('/theme', [AdminThemeController::class, 'show']);
    Route::put('/theme', [AdminThemeController::class, 'update']);

    Route::get('/routes', [AdminRouteController::class, 'index']);
    Route::put('/routes', [AdminRouteController::class, 'update']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:preferences'])->group(function (): void {
    Route::get('/languages', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'index']);
    Route::post('/languages', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'store']);
    Route::post('/languages/import', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'import']);
    Route::get('/languages/{language}', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'show']);
    Route::put('/languages/{language}', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'update']);
    Route::delete('/languages/{language}', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'destroy']);
    Route::get('/languages/{language}/export', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'export']);
    Route::get('/languages/{language}/translations', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'translations']);
    Route::put('/languages/{language}/translations/bulk', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'bulkUpdateTranslations']);
    Route::post('/languages/{language}/translations', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'upsertTranslation']);
    Route::delete('/languages/{language}/translations/{translation}', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminLanguageController::class, 'deleteTranslation']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'admin.pin.login', 'admin.pin.delete', 'admin.pin.settings', 'permission:admin_panel'])->group(function (): void {
    Route::get('/database/backup', [AdminDatabaseBackupController::class, 'download']);
    Route::get('/users/{user}/admin-pin', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminPinController::class, 'showUserPinStatus'])->whereNumber('user');
    Route::post('/users/{user}/admin-pin', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminPinController::class, 'setUserPin'])->whereNumber('user');
    Route::delete('/users/{user}/admin-pin', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminPinController::class, 'revokeUserPin'])->whereNumber('user');
    Route::put('/security/super-admin-pin', [\App\Modules\Selloff\Admin\Http\Controllers\Api\V1\Admin\AdminPinController::class, 'rotateSuperAdminPin']);
});
