<?php

namespace App\Modules\Selloff\Content\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ContentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'selloff-content');

        $apiRoutes = __DIR__.'/../Routes/api.php';
        if (file_exists($apiRoutes)) {
            Route::prefix('api/v1')->middleware('api')->group(function () use ($apiRoutes): void {
                $this->loadRoutesFrom($apiRoutes);
            });
        }

        $webRoutes = __DIR__.'/../Routes/web.php';
        if (file_exists($webRoutes)) {
            $this->loadRoutesFrom($webRoutes);
        }
    }

    public function register(): void
    {
        //
    }
}
