<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ResolvesEmpresa;
use App\Services\IndicadoresComercialesService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesEmpresa;

    public function __construct(
        private IndicadoresComercialesService $indicadores
    ) {}

    public function index(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $kpi       = $this->indicadores->resumenFacturasKpi($empresaId);

        return response()->json([
            'kpi' => [
                'total_emitidas'       => $kpi['total_emitidas'],
                'total_facturado'      => $kpi['total_facturado'],
                'total_recaudado'      => $kpi['total_recaudado'],
                'total_ventas_rapidas' => $kpi['total_ventas_rapidas'],
                'saldo_pendiente'      => $kpi['saldo_pendiente'],
                'facturas_con_saldo'   => $kpi['facturas_con_saldo'],
                'facturas_pagadas'     => $kpi['facturas_pagadas'],
            ],
        ]);
    }
}