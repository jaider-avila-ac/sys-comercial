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
        return response()->json(
            $this->auditoriaService->listarPorEmpresa($request->empresa_id_ctx)
        );
    }

    // GET /api/usuarios/{id}/auditoria
    public function porUsuario(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->auditoriaService->listarPorUsuario($id, $request->empresa_id_ctx)
        );
    }
}