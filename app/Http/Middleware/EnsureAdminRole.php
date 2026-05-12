<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (!$usuario || $usuario->rol === 'OPERATIVO') {
            return response()->json(
                ['message' => 'No tienes permisos para realizar esta acción.'],
                403
            );
        }

        return $next($request);
    }
}
