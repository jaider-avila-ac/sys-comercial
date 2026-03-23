<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SesionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SesionController extends Controller
{
    public function __construct(
        private readonly SesionService $sesionService,
    ) {}

    // GET /api/sesiones/activas
    // Usuarios con token Sanctum vigente en la empresa
    public function activas(Request $request): JsonResponse
    {
        return response()->json(
            $this->sesionService->sesionesActivas($request->empresa_id_ctx)
        );
    }

    // GET /api/sesiones/historial
    // Historial de logins/logouts de la empresa
    public function historial(Request $request): JsonResponse
    {
        return response()->json(
            $this->sesionService->historialLogin($request->empresa_id_ctx)
        );
    }

    // GET /api/sesiones/usuario/{id}
    // Historial de un usuario específico
    public function porUsuario(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->sesionService->historialPorUsuario($id, $request->empresa_id_ctx)
        );
    }
}
