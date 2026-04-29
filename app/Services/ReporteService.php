<?php

namespace App\Services;

use App\Models\EmpresaResumen;
use Illuminate\Support\Facades\DB;

class ReporteService
{
    /**
     * Obtiene reporte financiero completo por rango de fechas
     */
    public function getReporteFinanciero(int $empresaId, string $desde, string $hasta): array
    {
        // 1. Facturas del período
        $facturas = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'EMITIDA')
            ->whereBetween('fecha', [$desde, $hasta])
            ->select(
                'id',
                'numero',
                'fecha',
                'cliente_id',
                'subtotal',
                'total_iva as iva',
                'total',
                'total_pagado as pagado',
                DB::raw('total - total_pagado as saldo')
            )
            ->orderBy('fecha', 'desc')
            ->get();

        // Obtener nombres de clientes
        $clientesIds = $facturas->pluck('cliente_id')->unique()->filter();
        $clientes = [];
        if ($clientesIds->isNotEmpty()) {
            $clientes = DB::table('clientes')
                ->whereIn('id', $clientesIds)
                ->pluck('nombre_razon_social', 'id')
                ->toArray();
        }

        // Formatear facturas
        $facturasFormateadas = $facturas->map(function ($factura) use ($clientes) {
            return [
                'numero' => $factura->numero,
                'fecha' => $factura->fecha,
                'cliente' => $clientes[$factura->cliente_id] ?? 'Cliente no registrado',
                'subtotal' => round((float) $factura->subtotal, 2),
                'iva' => round((float) $factura->iva, 2),
                'total' => round((float) $factura->total, 2),
                'pagado' => round((float) $factura->pagado, 2),
                'saldo' => round((float) $factura->saldo, 2),
            ];
        });

        // 2. Totales del período
        $totalesPeriodo = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'EMITIDA')
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('
                COALESCE(SUM(total), 0) as total_facturado,
                COALESCE(SUM(total_pagado), 0) as total_pagado_facturas
            ')
            ->first();

        $totalFacturado = round((float) ($totalesPeriodo->total_facturado ?? 0), 2);
        $totalPagadoFacturas = round((float) ($totalesPeriodo->total_pagado_facturas ?? 0), 2);
        $saldoPendiente = $totalFacturado - $totalPagadoFacturas;

        // 3. Ingresos mostrador del período
        $ingresosMostrador = DB::table('ingresos_mostrador')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        // 4. Ingresos manuales del período
        $ingresosManuales = DB::table('ingresos_manuales')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        // 5. Total cobrado en el período
        $totalCobradoPeriodo = $totalPagadoFacturas + $ingresosMostrador + $ingresosManuales;

        // 6. Egresos del período
        $egresosCompras = DB::table('egresos_compras')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        $egresosManuales = DB::table('egresos_manuales')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        $totalEgresosPeriodo = round((float) ($egresosCompras + $egresosManuales), 2);

        // 7. Balance real del período
        $balanceRealPeriodo = $totalCobradoPeriodo - $totalEgresosPeriodo;

        // 8. Datos adicionales de compras
        $comprasContado = DB::table('compras')
            ->where('empresa_id', $empresaId)
            ->where('condicion_pago', 'CONTADO')
            ->where('estado', 'PAGADA')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('total');

        $creditoPendiente = DB::table('compras')
            ->where('empresa_id', $empresaId)
            ->where('condicion_pago', 'CREDITO')
            ->where('saldo_pendiente', '>', 0)
            ->sum('saldo_pendiente');

        return [
            'total_facturado' => $totalFacturado,
            'total_cobrado' => $totalCobradoPeriodo,
            'saldo_pendiente' => $saldoPendiente,
            'total_egresos' => $totalEgresosPeriodo,
            'balance_real' => $balanceRealPeriodo,
            'egresos_compras' => round((float) $egresosCompras, 2),
            'egresos_manuales' => round((float) $egresosManuales, 2),
            'compras_contado' => round((float) $comprasContado, 2),
            'credito_pendiente' => round((float) $creditoPendiente, 2),
            'facturas' => $facturasFormateadas,
        ];
    }
}