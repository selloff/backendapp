<?php

namespace App\Modules\Selloff\Catalog\Providers;

use App\Modules\Selloff\Catalog\Console\BackfillProductApprovedSnapshotsCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillProductApprovedSnapshotsCommand::class,
            ]);
        }

        $routes = __DIR__.'/../Routes/api.php';
        if (file_exists($routes)) {
            Route::prefix('api/v1')->middleware('api')->group(function () use ($routes): void {
                $this->loadRoutesFrom($routes);
            });
        }
    }

    public function register(): void
    {
        //
    }
}
