<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class SelloffPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $base = base_path('packages/selloff');
        if (! File::isDirectory($base)) {
            return;
        }

        foreach (File::directories($base) as $moduleDir) {
            $module = basename($moduleDir);
            $provider = "App\\Modules\\Selloff\\{$module}\\Providers\\{$module}ServiceProvider";
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        //
    }
}
