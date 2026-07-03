<?php

namespace App\Modules\Selloff\Payout\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PayoutServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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
