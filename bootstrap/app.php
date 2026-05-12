<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Validation\ValidationException;
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
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'check.active.token' => \App\Http\Middleware\CheckActiveToken::class,
            'role.admin'         => \App\Http\Middleware\EnsureAdminRole::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (\Throwable $e, $request) {

            $isApi = $request->is('api/*') || $request->expectsJson();

            // Validación — exponer los errores de campos es correcto y necesario
            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'Los datos proporcionados no son válidos.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
            } elseif ($e instanceof AuthenticationException) {
                $status = 401;
            } else {
                $status = 500;
            }

            // Mensajes de error saneados — nunca exponer rutas internas, clases ni stack traces
            $message = match (true) {
                $status >= 500                                        => 'Error interno del servidor.',
                $status === 404                                       => 'No encontrado.',
                $status === 401                                       => $e->getMessage() ?: 'No autenticado.',
                $status === 403                                       => $e->getMessage() ?: 'Acceso denegado.',
                $status === 409                                       => $e->getMessage() ?: 'Conflicto de datos.',
                $e instanceof HttpExceptionInterface && $e->getMessage() => $e->getMessage(),
                default                                              => 'Error del servidor.',
            };

            if ($isApi) {
                return response()->json(['message' => $message], $status);
            }

            // Rutas web — nunca mostrar debug HTML a visitantes
            return response($status === 404 ? 'Not Found' : 'Error', $status);
        });
    })

    ->create();
