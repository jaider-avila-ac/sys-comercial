<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IngresoUnificadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngresoUnificadoController extends Controller
{
    public function __construct(
        private readonly IngresoUnificadoService $ingresoUnificadoService,
    ) {}

    /**
     * GET /api/ingresos/unificados
     * Lista todos los ingresos (pagos, mostrador, manuales) unificados
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->query('search'),
            'desde'  => $request->query('desde'),
            'hasta'  => $request->query('hasta'),
        ];

        $perPage = (int) $request->query('per_page', 20);

        return response()->json(
            $this->ingresoUnificadoService->listar(
                $request->empresa_id_ctx,
                $filters,
                $perPage
            )
        );
    }
}