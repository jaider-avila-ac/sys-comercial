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

    // GET /api/usuarios/{id}/sesiones
    public function porUsuario(Request $request, $id): JsonResponse
    {
        $usuarioId = (int) $id;
        
        $filters = [
            'desde' => $request->query('desde'),
            'hasta' => $request->query('hasta'),
        ];
        
        $perPage = (int) $request->query('per_page', 20);
        
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        
        return response()->json(
            $this->sesionService->historialPorUsuario(
                $usuarioId,
                $request->empresa_id_ctx,
                $filters,
                $perPage
            )
        );
    }
}