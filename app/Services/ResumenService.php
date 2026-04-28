<?php

namespace App\Services;

use App\Models\EmpresaResumen;
use Illuminate\Support\Facades\DB;

class ResumenService
{
    public function recalcular(int $empresaId): void
    {
        $data = $this->calcular($empresaId);

        EmpresaResumen::updateOrInsert(
            ['empresa_id' => $empresaId],
            $data
        );
    }

    private function calcular(int $empresaId): array
    {
        // ── Clientes e ítems activos ──────────────────────────────────────────
        $totalClientes = DB::table('clientes')
            ->where('empresa_id', $empresaId)
            ->where('is_activo', true)
            ->count();

        $totalItems = DB::table('items')
            ->where('empresa_id', $empresaId)
            ->where('is_activo', true)
            ->count();

        // ── Cotizaciones ──────────────────────────────────────────────────────
        $cotizacionesActivas = DB::table('cotizaciones')
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', ['BORRADOR', 'EMITIDA'])
            ->count();

        // ── Facturas ──────────────────────────────────────────────────────────
        $facturasBorrador = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'BORRADOR')
            ->count();

        $facturasEmitidas = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'EMITIDA')
            ->count();

        $totalesFacturas = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'EMITIDA')
            ->selectRaw('
                COALESCE(SUM(total), 0)        as total_facturado,
                COALESCE(SUM(total_pagado), 0) as total_pagado,
                COALESCE(SUM(saldo), 0)        as saldo_pendiente
            ')
            ->first();

        // ── Ingresos que sí entran a caja directa ────────────────────────────
        $ingresosFacturas = DB::table('caja_movimientos')
            ->where('empresa_id', $empresaId)
            ->where('origen_tipo', 'INGRESO_PAGO')
            ->sum('monto');

        $ingresosMostrador = DB::table('caja_movimientos')
            ->where('empresa_id', $empresaId)
            ->where('origen_tipo', 'INGRESO_MOSTRADOR')
            ->sum('monto');

        // ── Ingresos manuales reales (solo activos) ──────────────────────────
        $ingresosManuales = DB::table('ingresos_manuales')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->sum('monto');

        // ── Total en caja = facturas + mostrador ─────────────────────────────
        $totalEnCaja = $ingresosFacturas + $ingresosMostrador;

        // ── Egresos de compras ────────────────────────────────────────────────
        $egresosCompras = DB::table('caja_movimientos')
            ->where('empresa_id', $empresaId)
            ->where('origen_tipo', 'EGRESO_COMPRA')
            ->sum('monto');

        // ── Egresos manuales reales (solo activos) ───────────────────────────
        $egresosManuales = DB::table('egresos_manuales')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->sum('monto');

        $totalEgresos = $egresosCompras + $egresosManuales;

        // ── Balance real = caja + ingresos manuales − egresos ───────────────
        $balanceReal = $totalEnCaja + $ingresosManuales - $totalEgresos;

        return [
            'empresa_id'           => $empresaId,
            'total_clientes'       => $totalClientes,
            'total_items'          => $totalItems,
            'cotizaciones_activas' => $cotizacionesActivas,
            'facturas_borrador'    => $facturasBorrador,
            'facturas_emitidas'    => $facturasEmitidas,
            'total_facturado'      => round((float) ($totalesFacturas->total_facturado ?? 0), 2),
            'total_pagado'         => round((float) ($totalesFacturas->total_pagado ?? 0), 2),
            'saldo_pendiente'      => round((float) ($totalesFacturas->saldo_pendiente ?? 0), 2),
            'ingresos_facturas'    => round((float) $ingresosFacturas, 2),
            'ingresos_mostrador'   => round((float) $ingresosMostrador, 2),
            'ingresos_manuales'    => round((float) $ingresosManuales, 2),
            'total_en_caja'        => round((float) $totalEnCaja, 2),
            'egresos_compras'      => round((float) $egresosCompras, 2),
            'egresos_manuales_tot' => round((float) $egresosManuales, 2),
            'total_egresos'        => round((float) $totalEgresos, 2),
            'balance_real'         => round((float) $balanceReal, 2),
            'ultima_actividad'     => now(),
        ];
    }
}