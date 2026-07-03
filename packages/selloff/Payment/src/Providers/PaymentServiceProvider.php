<?php

namespace App\Modules\Selloff\Payment\Providers;

use App\Modules\Selloff\Payment\Contracts\StripeGatewayInterface;
use App\Modules\Selloff\Payment\Gateways\FakeStripeGateway;
use App\Modules\Selloff\Payment\Gateways\StripeGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
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
        $this->app->bind(StripeGatewayInterface::class, function ($app) {
            if ($app->environment('testing')) {
                return new FakeStripeGateway;
            }

            return new StripeGateway;
        });
    }
}
