<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Aquí se configura toda la aplicación en Laravel 11.
| Ya no se usa config/app.php para providers personalizados.
|
*/

return Application::configure(basePath: dirname(__DIR__))

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    /*
    |--------------------------------------------------------------------------
    | Providers personalizados (IMPORTANTE)
    |--------------------------------------------------------------------------
    | Aquí registramos AuthServiceProvider para Policies.
    */
    ->withProviders([
        App\Providers\AuthServiceProvider::class,
    ])

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    | Sanctum reconoce peticiones SPA desde React.
    */
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

    })

    /*
    |--------------------------------------------------------------------------
    | Exceptions
    |--------------------------------------------------------------------------
    */
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })

    ->create();