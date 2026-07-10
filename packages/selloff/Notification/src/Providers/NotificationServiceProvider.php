<?php

namespace App\Modules\Selloff\Notification\Providers;

use App\Modules\Selloff\Notification\Console\ProcessEmailJobsCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'selloff-notification');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessEmailJobsCommand::class,
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
