<?php

use App\LegacyImport\Data\LegacyRouteSlugs;
use App\Modules\Selloff\Content\Http\Controllers\RssFeedController;
use Illuminate\Support\Facades\Route;

$routeSlug = static function (string $routeKey): string {
    foreach (LegacyRouteSlugs::rows() as $row) {
        if ($row['route_key'] === $routeKey) {
            return $row['slug'];
        }
    }

    return str_replace('_', '-', $routeKey);
};

Route::get($routeSlug('rss_feeds'), [RssFeedController::class, 'index']);

Route::prefix('rss')->group(function () use ($routeSlug): void {
    Route::get($routeSlug('latest_products'), [RssFeedController::class, 'latest']);
    Route::get($routeSlug('featured_products'), [RssFeedController::class, 'featured']);
    Route::get($routeSlug('category').'/{slug}', [RssFeedController::class, 'category']);
    Route::get($routeSlug('seller').'/{slug}', [RssFeedController::class, 'seller']);
});
