<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

  




    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(HandleCors::class);

        $middleware->alias([
            'empresa.context' => \App\Http\Middleware\ResolveEmpresaContext::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (\Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // HttpExceptionInterface (HttpException, NotFoundHttpException, etc.)
            // tiene getStatusCode() — verificamos con instanceof, no method_exists
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
            } else {
                $status = 500;
            }

            return response()->json(
                ['message' => $e->getMessage() ?: 'Error del servidor.'],
                $status
            );
        });
    })

    ->create();
