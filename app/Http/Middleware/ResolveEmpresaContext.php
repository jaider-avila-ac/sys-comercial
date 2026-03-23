<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveEmpresaContext
 *
 * Inyecta `empresa_id` en el request a partir del usuario autenticado.
 * El SUPER_ADMIN puede pasar X-Empresa-Id en el header para operar
 * sobre cualquier empresa. Los demás roles solo operan sobre la suya.
 */
class ResolveEmpresaContext
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Usuario $usuario */
        $usuario = $request->user();

        if (! $usuario) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if ($usuario->esSuperAdmin()) {
            // SUPER_ADMIN puede indicar la empresa vía header o query param
            $empresaId = $request->header('X-Empresa-Id')
                ?? $request->query('empresa_id')
                ?? $usuario->empresa_id;
        } else {
            // Cualquier otro rol solo opera en su propia empresa
            $empresaId = $usuario->empresa_id;
        }

        // Disponible en toda la cadena como $request->empresa_id_ctx
        $request->merge(['empresa_id_ctx' => (int) $empresaId]);

        return $next($request);
    }
}