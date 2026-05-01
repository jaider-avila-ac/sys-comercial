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
     * Reporte financiero completo (KPIs + Rendimiento de ítems)
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

    /**
     * GET /api/reportes/kpis
     * SOLO KPIs financieros (sin rendimiento de ítems)
     */
    public function kpis(Request $request): JsonResponse
    {
        $data = $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $resultado = $this->reporteService->getKPIs(
            $request->empresa_id_ctx,
            $data['desde'],
            $data['hasta']
        );

        return response()->json($resultado);
    }

    /**
     * GET /api/reportes/rendimiento-items
     * SOLO rendimiento de ítems (sin KPIs)
     */
    public function rendimientoItems(Request $request): JsonResponse
    {
        $data = $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
        ]);

        $resultado = $this->reporteService->getRendimientoItemsReporte(
            $request->empresa_id_ctx,
            $data['desde'],
            $data['hasta']
        );

        return response()->json([
            'rendimiento_items' => $resultado,
            'resumen' => [
                'desde' => $data['desde'],
                'hasta' => $data['hasta'],
                'total_items' => count($resultado),
            ],
        ]);
    }
}