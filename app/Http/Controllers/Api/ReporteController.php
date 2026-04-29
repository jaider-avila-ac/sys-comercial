<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function __construct(
        private readonly ReporteService $reporteService,
    ) {}

    /**
     * GET /api/reportes/financiero
     * Reporte financiero por rango de fechas
     */
    public function financiero(Request $request): JsonResponse
    {
        $data = $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $resultado = $this->reporteService->getReporteFinanciero(
            $request->empresa_id_ctx,
            $data['desde'],
            $data['hasta']
        );

        return response()->json($resultado);
    }
}