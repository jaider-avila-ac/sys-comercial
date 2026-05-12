<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Eliminar headers que revelan tecnología
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Prevenir que el navegador adivine el tipo MIME
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevenir clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Filtro XSS del navegador (legacy, refuerzo adicional)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // No enviar referrer a sitios externos
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Deshabilitar funcionalidades del navegador que no se usan
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Forzar HTTPS por 1 año (solo cuando ya se está en HTTPS)
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
