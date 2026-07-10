<?php

namespace App\Providers;

use App\Console\Commands\MigrateFreshCommand;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Console\Migrations\FreshCommand as LaravelFreshCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LaravelFreshCommand::class, function ($app) {
            return new MigrateFreshCommand($app['migrator']);
        });
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('selloff.rate_limits.api_per_minute', 120))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute((int) config('selloff.rate_limits.auth_per_minute', 20))
                ->by($request->ip());
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $base = rtrim((string) config('selloff.spa_url', config('app.url')), '/');

            return $base.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }
}
