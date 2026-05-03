<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }
        
        // Verificar si el usuario está activo
        if (!$user->is_activo) {
            return response()->json([
                'message' => 'Usuario desactivado. Contacta al administrador.',
                'code' => 'USER_INACTIVE'
            ], 401);
        }
        
        // Verificar si el token todavía existe en la base de datos
        $token = $user->currentAccessToken();
        if (!$token || !$user->tokens()->where('id', $token->id)->exists()) {
            return response()->json([
                'message' => 'Sesión expirada. Por favor, inicia sesión nuevamente.',
                'code' => 'TOKEN_REVOKED'
            ], 401);
        }
        
        return $next($request);
    }
}