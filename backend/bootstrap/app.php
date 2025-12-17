<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Admin Module Routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('app/Modules/Admin/Routes/api.php'));

            // Laundry Module Routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('app/Modules/Laundry/Routes/api.php'));

            // Resto Module Routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('app/Modules/Resto/Routes/api.php'));

            // Depot Module Routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('app/Modules/Depot/Routes/api.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
