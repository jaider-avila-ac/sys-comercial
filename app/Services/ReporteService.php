<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReporteService
{
    /**
     * Obtiene SOLO KPIs financieros por rango de fechas
     */
    public function getKPIs(int $empresaId, string $desde, string $hasta): array
    {
        // ── Facturas en el período ──────────────────────────────────────────
        $totalesFacturas = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'EMITIDA')
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('
                COALESCE(SUM(total), 0) as total_facturado,
                COALESCE(SUM(total_pagado), 0) as total_cobrado,
                COALESCE(SUM(saldo), 0) as saldo_pendiente
            ')
            ->first();

        // ── INGRESOS en el período ──────────────────────────────────────────
        $ingresosFacturas = DB::table('ingresos_pagos')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        $ingresosMostrador = DB::table('ingresos_mostrador')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        $ingresosManuales = DB::table('ingresos_manuales')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('monto');

        $totalIngresos = $ingresosFacturas + $ingresosMostrador + $ingresosManuales;

        // ── EGRESOS en el período ───────────────────────────────────────────
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

        $totalEgresos = $egresosCompras + $egresosManuales;

        // ── Compras de contado en el período ────────────────────────────────
        $comprasContado = DB::table('compras')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'PAGADA')
            ->where('condicion_pago', 'CONTADO')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('total');

        $balanceReal = $totalIngresos - $totalEgresos;

        return [
            'total_facturado' => round((float) $totalesFacturas->total_facturado, 2),
            'total_cobrado' => round((float) $totalesFacturas->total_cobrado, 2),
            'saldo_pendiente' => round((float) $totalesFacturas->saldo_pendiente, 2),
            'ingresos_facturas' => round((float) $ingresosFacturas, 2),
            'ingresos_mostrador' => round((float) $ingresosMostrador, 2),
            'ingresos_manuales' => round((float) $ingresosManuales, 2),
            'total_ingresos' => round((float) $totalIngresos, 2),
            'egresos_compras' => round((float) $egresosCompras, 2),
            'egresos_manuales' => round((float) $egresosManuales, 2),
            'total_egresos' => round((float) $totalEgresos, 2),
            'compras_contado' => round((float) $comprasContado, 2),
            'credito_pendiente' => round((float) $totalesFacturas->saldo_pendiente, 2),
            'balance_real' => round((float) $balanceReal, 2),
            'resumen' => [
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        ];
    }

    /**
     * Obtiene SOLO rendimiento de ítems por rango de fechas
     */
    public function getRendimientoItemsReporte(int $empresaId, string $desde, string $hasta): array
    {
        // 1. Ventas en FACTURAS (usando campos correctos: iva_valor, total_linea)
        $ventasFacturas = DB::table('factura_lineas')
            ->join('facturas', 'factura_lineas.factura_id', '=', 'facturas.id')
            ->join('items', 'factura_lineas.item_id', '=', 'items.id')
            ->where('facturas.empresa_id', $empresaId)
            ->where('facturas.estado', 'EMITIDA')
            ->whereBetween('facturas.fecha', [$desde, $hasta])
            ->select(
                'items.id as item_id',
                'items.nombre as item_nombre',
                'items.tipo as item_tipo',
                'items.precio_compra',
                'items.unidad',
                DB::raw('COALESCE(SUM(factura_lineas.cantidad), 0) as cantidad_vendida'),
                DB::raw('COALESCE(SUM(factura_lineas.valor_unitario * factura_lineas.cantidad), 0) as total_subtotal'),
                DB::raw('COALESCE(SUM(factura_lineas.descuento), 0) as total_descuento'),
                DB::raw('COALESCE(SUM(factura_lineas.iva_valor), 0) as total_iva'),
                DB::raw('COALESCE(SUM(factura_lineas.total_linea), 0) as total_ventas')
            )
            ->groupBy('items.id', 'items.nombre', 'items.tipo', 'items.precio_compra', 'items.unidad')
            ->get();

        // 2. Ventas en MOSTRADOR
        $ventasMostrador = DB::table('ingresos_mostrador')
            ->join('items', 'ingresos_mostrador.item_id', '=', 'items.id')
            ->where('ingresos_mostrador.empresa_id', $empresaId)
            ->where('ingresos_mostrador.estado', 'ACTIVO')
            ->whereBetween('ingresos_mostrador.fecha', [$desde, $hasta])
            ->select(
                'items.id as item_id',
                'items.nombre as item_nombre',
                'items.tipo as item_tipo',
                'items.precio_compra',
                'items.unidad',
                DB::raw('COALESCE(SUM(ingresos_mostrador.cantidad), 0) as cantidad_vendida'),
                DB::raw('COALESCE(SUM(ingresos_mostrador.precio_unitario * ingresos_mostrador.cantidad), 0) as total_subtotal'),
                DB::raw('0 as total_descuento'),
                DB::raw('COALESCE(SUM(ingresos_mostrador.precio_unitario * ingresos_mostrador.cantidad * (ingresos_mostrador.iva_pct / 100)), 0) as total_iva'),
                DB::raw('COALESCE(SUM(ingresos_mostrador.monto), 0) as total_ventas')
            )
            ->groupBy('items.id', 'items.nombre', 'items.tipo', 'items.precio_compra', 'items.unidad')
            ->get();

        // Combinar resultados
        $resultados = [];

        foreach ($ventasFacturas as $item) {
            $resultados[$item->item_id] = [
                'item_id' => $item->item_id,
                'item_nombre' => $item->item_nombre,
                'item_tipo' => $item->item_tipo,
                'precio_compra' => round((float) $item->precio_compra, 2),
                'unidad' => $item->unidad ?? 'UND',
                'cantidad_vendida' => (int) $item->cantidad_vendida,
                'total_subtotal' => round((float) $item->total_subtotal, 2),
                'total_descuento' => round((float) $item->total_descuento, 2),
                'total_iva' => round((float) $item->total_iva, 2),
                'total_ventas' => round((float) $item->total_ventas, 2),
            ];
        }

        foreach ($ventasMostrador as $item) {
            if (isset($resultados[$item->item_id])) {
                $resultados[$item->item_id]['cantidad_vendida'] += (int) $item->cantidad_vendida;
                $resultados[$item->item_id]['total_subtotal'] += (float) $item->total_subtotal;
                $resultados[$item->item_id]['total_descuento'] += (float) $item->total_descuento;
                $resultados[$item->item_id]['total_iva'] += (float) $item->total_iva;
                $resultados[$item->item_id]['total_ventas'] += (float) $item->total_ventas;
            } else {
                $resultados[$item->item_id] = [
                    'item_id' => $item->item_id,
                    'item_nombre' => $item->item_nombre,
                    'item_tipo' => $item->item_tipo,
                    'precio_compra' => round((float) $item->precio_compra, 2),
                    'unidad' => $item->unidad ?? 'UND',
                    'cantidad_vendida' => (int) $item->cantidad_vendida,
                    'total_subtotal' => round((float) $item->total_subtotal, 2),
                    'total_descuento' => round((float) $item->total_descuento, 2),
                    'total_iva' => round((float) $item->total_iva, 2),
                    'total_ventas' => round((float) $item->total_ventas, 2),
                ];
            }
        }

        // Calcular stock disponible y ganancias
        $resultadoFinal = [];
        foreach ($resultados as $itemId => $item) {
            $inventario = DB::table('inventarios')
                ->where('empresa_id', $empresaId)
                ->where('item_id', $itemId)
                ->first();
            
            $cantidadDisponible = $inventario ? (int) $inventario->unidades_actuales : 0;
            $totalCosto = $item['precio_compra'] * $item['cantidad_vendida'];
            $gananciaNeta = $item['total_ventas'] - $totalCosto;
            $valorUnitarioPromedio = $item['cantidad_vendida'] > 0 
                ? round($item['total_ventas'] / $item['cantidad_vendida'], 2) 
                : 0;

            $resultadoFinal[] = [
                'item_id' => $item['item_id'],
                'item_nombre' => $item['item_nombre'],
                'item_tipo' => $item['item_tipo'],
                'precio_compra' => $item['precio_compra'],
                'valor_unitario_promedio' => $valorUnitarioPromedio,
                'unidad' => $item['unidad'],
                'cantidad_disponible' => $cantidadDisponible,
                'cantidad_vendida' => $item['cantidad_vendida'],
                'total_subtotal' => $item['total_subtotal'],
                'total_descuento' => $item['total_descuento'],
                'total_iva' => $item['total_iva'],
                'total_ventas' => $item['total_ventas'],
                'total_costo' => round($totalCosto, 2),
                'ganancia_neta' => round($gananciaNeta, 2),
                'margen_ganancia' => $item['total_ventas'] > 0 
                    ? round(($gananciaNeta / $item['total_ventas']) * 100, 2) 
                    : 0,
            ];
        }

        usort($resultadoFinal, fn($a, $b) => $b['ganancia_neta'] <=> $a['ganancia_neta']);
        return $resultadoFinal;
    }

    /**
     * Obtiene reporte financiero completo (KPIs + Rendimiento)
     */
    public function getReporteFinanciero(int $empresaId, string $desde, string $hasta): array
    {
        $kpis = $this->getKPIs($empresaId, $desde, $hasta);
        $rendimientoItems = $this->getRendimientoItemsReporte($empresaId, $desde, $hasta);

        return array_merge($kpis, [
            'rendimiento_items' => $rendimientoItems,
        ]);
    }
}