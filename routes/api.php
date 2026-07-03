<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    foreach (glob(__DIR__.'/api/v1/*.php') as $routeFile) {
        require $routeFile;
    }
});
