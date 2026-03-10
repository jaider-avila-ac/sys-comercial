<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ResolvesEmpresa;
use App\Services\IndicadoresComercialesService;
use Illuminate\Http\Request;

class ReportesController extends Controller
{
    use ResolvesEmpresa;

    public function __construct(
        private IndicadoresComercialesService $indicadores
    ) {}

    /**
     * GET /api/reportes/ventas-resumen
     */
    public function ventasResumen(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $r = $this->validateRango($request);

        return response()->json([
            'desde' => $r['desde'],
            'hasta' => $r['hasta'],
            ...$this->indicadores->resumenFacturacion(
                $empresaId,
                $r['desde'],
                $r['hasta']
            ),
        ]);
    }

    /**
     * GET /api/reportes/recaudos-resumen
     */
    public function recaudosResumen(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $r = $this->validateRango($request);

        return response()->json([
            'desde' => $r['desde'],
            'hasta' => $r['hasta'],
            ...$this->indicadores->resumenIngresos(
                $empresaId,
                $r['desde'],
                $r['hasta']
            ),
        ]);
    }

    /**
     * GET /api/reportes/saldo-al-cierre
     */
    public function saldoAlCierre(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'desde'  => ['required', 'date'],
            'hasta'  => ['required', 'date', 'after_or_equal:desde'],
            'cierre' => ['required', 'date'],
        ]);

        return response()->json([
            'desde'  => $data['desde'],
            'hasta'  => $data['hasta'],
            'cierre' => $data['cierre'],
            ...$this->indicadores->saldoAlCierre(
                $empresaId,
                $data['desde'],
                $data['hasta'],
                $data['cierre']
            ),
        ]);
    }

    /**
     * GET /api/reportes/ventas-lineas
     */
    public function ventasLineas(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $r = $this->validateRango($request);

        return response()->json(
            $this->indicadores->ventasLineas(
                $empresaId,
                $r['desde'],
                $r['hasta']
            )
        );
    }

    /**
     * GET /api/reportes/flujo-mensual
     */
    public function flujoMensual(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $r = $this->validateRango($request);

        return response()->json([
            'desde' => $r['desde'],
            'hasta' => $r['hasta'],
            'data'  => $this->indicadores->flujoMensual(
                $empresaId,
                $r['desde'],
                $r['hasta']
            ),
        ]);
    }

    private function validateRango(Request $request): array
    {
        return $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);
    }
}