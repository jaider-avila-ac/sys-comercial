<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EgresoUnificadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EgresoUnificadoController extends Controller
{
    public function __construct(
        private readonly EgresoUnificadoService $egresoUnificadoService,
    ) {}

    /**
     * GET /api/egresos/unificados
     * Lista todos los egresos (compras y manuales) unificados
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'tipo'   => $request->query('tipo'),
            'estado' => $request->query('estado'),
            'desde'  => $request->query('desde'),
            'hasta'  => $request->query('hasta'),
        ];

        $perPage = (int) $request->query('per_page', 20);

        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        return response()->json(
            $this->egresoUnificadoService->listar(
                $request->empresa_id_ctx,
                $filters,
                $perPage
            )
        );
    }
}