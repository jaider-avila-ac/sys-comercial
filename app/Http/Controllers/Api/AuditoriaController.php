<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
    ) {}

    // GET /api/auditoria
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'accion' => $request->query('accion'),
            'desde'  => $request->query('desde'),
            'hasta'  => $request->query('hasta'),
        ];
        
        $perPage = (int) $request->query('per_page', 20);
        
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        
        return response()->json(
            $this->auditoriaService->listarPorEmpresa(
                $request->empresa_id_ctx,
                $filters,
                $perPage
            )
        );
    }

    // GET /api/usuarios/{id}/auditoria
    public function porUsuario(Request $request, $id): JsonResponse
    {
        $usuarioId = (int) $id;
        
        $filters = [
            'accion' => $request->query('accion'),
            'desde'  => $request->query('desde'),
            'hasta'  => $request->query('hasta'),
        ];
        
        $perPage = (int) $request->query('per_page', 20);
        
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        
        return response()->json(
            $this->auditoriaService->listarPorUsuario(
                $usuarioId,
                $request->empresa_id_ctx,
                $filters,
                $perPage
            )
        );
    }
}