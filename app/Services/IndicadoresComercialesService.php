<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class IndicadoresComercialesService
{
    public function resumenFacturacion(?int $empresaId, string $desde, string $hasta): array
    {
        $q = DB::table('facturas as f')
            ->where('f.estado', 'EMITIDA')
            ->whereDate('f.fecha', '>=', $desde)
            ->whereDate('f.fecha', '<=', $hasta);

        if ($empresaId) {
            $q->where('f.empresa_id', $empresaId);
        }

        $row = $q->selectRaw('
            COUNT(*) as facturas,
            COALESCE(SUM(f.subtotal), 0)         as subtotal,
            COALESCE(SUM(f.total_descuentos), 0) as total_descuentos,
            COALESCE(SUM(f.total_iva), 0)        as total_iva,
            COALESCE(SUM(f.total), 0)            as total_facturado,
            COALESCE(SUM(f.total_pagado), 0)     as total_pagado_actual,
            COALESCE(SUM(f.saldo), 0)            as saldo_actual
        ')->first();

        return [
            'facturas'            => (int)($row->facturas ?? 0),
            'subtotal'            => (float)($row->subtotal ?? 0),
            'total_descuentos'    => (float)($row->total_descuentos ?? 0),
            'total_iva'           => (float)($row->total_iva ?? 0),
            'total_facturado'     => (float)($row->total_facturado ?? 0),
            'total_pagado_actual' => (float)($row->total_pagado_actual ?? 0),
            'saldo_actual'        => (float)($row->saldo_actual ?? 0),
        ];
    }

   public function resumenIngresos(?int $empresaId, string $desde, string $hasta): array
{
    $qMostrador = DB::table('pagos as p')
        ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
        ->where('c.nombre_razon_social', 'MOSTRADOR')
        ->whereDate('p.fecha', '>=', $desde)
        ->whereDate('p.fecha', '<=', $hasta);

    if ($empresaId) {
        $qMostrador->where('p.empresa_id', $empresaId);
    }

    $mostrador = $qMostrador->selectRaw('
        COUNT(p.id) as pagos_mostrador,
        COALESCE(SUM(p.total_pagado), 0) as ingresos_mostrador
    ')->first();

    // ← ya no se hace leftJoin con pago_aplicaciones
    //   se usa p.total_pagado que es el dinero real recibido
    $qFacturas = DB::table('pagos as p')
        ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
        ->where('c.nombre_razon_social', '<>', 'MOSTRADOR')
        ->whereDate('p.fecha', '>=', $desde)
        ->whereDate('p.fecha', '<=', $hasta);

    if ($empresaId) {
        $qFacturas->where('p.empresa_id', $empresaId);
    }

    $facturas = $qFacturas->selectRaw('
        COUNT(p.id) as pagos_facturas,
        COALESCE(SUM(p.total_pagado), 0) as ingresos_facturas
    ')->first();

    $qIng = DB::table('ingresos_manuales')
        ->whereDate('fecha', '>=', $desde)
        ->whereDate('fecha', '<=', $hasta);

    if ($empresaId) {
        $qIng->where('empresa_id', $empresaId);
    }

    $ingresosManuales = (float) $qIng->sum('monto');

    $qEgr = DB::table('egresos')
        ->whereDate('fecha', '>=', $desde)
        ->whereDate('fecha', '<=', $hasta);

    if ($empresaId) {
        $qEgr->where('empresa_id', $empresaId);
    }

    $egresos = (float) $qEgr->sum('monto');

    $ingresosFacturas  = (float)($facturas->ingresos_facturas ?? 0);
    $ingresosMostrador = (float)($mostrador->ingresos_mostrador ?? 0);

    $totalEnCaja   = $ingresosFacturas + $ingresosMostrador;
    $totalIngresos = $totalEnCaja + $ingresosManuales;
    $balanceReal   = $totalIngresos - $egresos;

    return [
        'pagos_facturas'     => (int)($facturas->pagos_facturas ?? 0),
        'pagos_mostrador'    => (int)($mostrador->pagos_mostrador ?? 0),
        'ingresos_facturas'  => $ingresosFacturas,
        'ingresos_mostrador' => $ingresosMostrador,
        'ingresos_manuales'  => $ingresosManuales,
        'total_en_caja'      => $totalEnCaja,
        'total_ingresos'     => $totalIngresos,
        'total_egresos'      => $egresos,
        'balance_real'       => $balanceReal,
    ];
}

    public function saldoAlCierre(?int $empresaId, string $desde, string $hasta, string $cierre): array
    {
        $aplicadoSub = DB::table('pago_aplicaciones as pa')
            ->join('pagos as p', 'p.id', '=', 'pa.pago_id')
            ->selectRaw('pa.factura_id, SUM(pa.monto) as aplicado_hasta_cierre')
            ->whereDate('p.fecha', '<=', $cierre)
            ->groupBy('pa.factura_id');

        $q = DB::table('facturas as f')
            ->leftJoinSub($aplicadoSub, 'ap', fn($j) => $j->on('ap.factura_id', '=', 'f.id'))
            ->where('f.estado', 'EMITIDA')
            ->whereDate('f.fecha', '>=', $desde)
            ->whereDate('f.fecha', '<=', $hasta);

        if ($empresaId) {
            $q->where('f.empresa_id', $empresaId);
        }

        $row = $q->selectRaw('
            COUNT(*) as facturas,
            COALESCE(SUM(f.total), 0) as total_facturado,
            COALESCE(SUM(ap.aplicado_hasta_cierre), 0) as total_aplicado_hasta_cierre,
            COALESCE(SUM(GREATEST(0, f.total - COALESCE(ap.aplicado_hasta_cierre, 0))), 0) as saldo_al_cierre
        ')->first();

        return [
            'facturas'                    => (int)($row->facturas ?? 0),
            'total_facturado'             => (float)($row->total_facturado ?? 0),
            'total_aplicado_hasta_cierre' => (float)($row->total_aplicado_hasta_cierre ?? 0),
            'saldo_al_cierre'             => (float)($row->saldo_al_cierre ?? 0),
        ];
    }

    public function ventasLineas(?int $empresaId, string $desde, string $hasta)
    {
        // ── 1) Ventas por factura ─────────────────────────────────────
        $facturas = DB::table('factura_lineas as fl')
            ->join('facturas as f', 'f.id', '=', 'fl.factura_id')
            ->leftJoin('items as it', 'it.id', '=', 'fl.item_id')
            ->selectRaw("
            'FACTURA' as origen,
            f.id as documento_id,
            f.numero as documento_numero,
            f.fecha as documento_fecha,
            f.numero as factura_numero,
            f.fecha as factura_fecha,
            fl.item_id as item_id,
            COALESCE(it.nombre, '') as item_nombre,
            fl.descripcion_manual as descripcion_manual,
            fl.cantidad as cantidad,
            fl.valor_unitario as valor_unitario,
            fl.iva_pct as iva_pct,
            fl.iva_valor as iva_valor,
            fl.total_linea as total_linea,
            f.estado as documento_estado,
            f.total as documento_total,
            f.total_pagado as documento_total_pagado,
            f.saldo as documento_saldo
        ")
            ->where('f.estado', 'EMITIDA')
            ->whereDate('f.fecha', '>=', $desde)
            ->whereDate('f.fecha', '<=', $hasta);

        if ($empresaId) {
            $facturas->where('f.empresa_id', $empresaId);
        }

        $mostrador = DB::table('pagos as p')
            ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
            ->selectRaw("
            'MOSTRADOR' as origen,
            p.id as documento_id,
            p.numero_recibo as documento_numero,
            p.fecha as documento_fecha,
            p.numero_recibo as factura_numero,
            p.fecha as factura_fecha,
            NULL as item_id,
            'VENTA RÁPIDA' as item_nombre,
            p.notas as descripcion_manual,
            1 as cantidad,
            p.total_pagado as valor_unitario,
            0 as iva_pct,
            0 as iva_valor,
            p.total_pagado as total_linea,
            'PAGADA' as documento_estado,
            p.total_pagado as documento_total,
            p.total_pagado as documento_total_pagado,
            0 as documento_saldo
        ")
            ->where('c.nombre_razon_social', 'MOSTRADOR')
            ->whereDate('p.fecha', '>=', $desde)
            ->whereDate('p.fecha', '<=', $hasta);

        if ($empresaId) {
            $mostrador->where('p.empresa_id', $empresaId);
        }

        // ── 3) Unión de ambas fuentes ─────────────────────────────────
        $union = $facturas->unionAll($mostrador);

        return DB::query()
            ->fromSub($union, 'movs')
            ->orderBy('documento_fecha')
            ->orderBy('documento_numero')
            ->paginate(100);
    }
    public function resumenFacturasKpi(?int $empresaId): array
    {
        $facQ = DB::table('facturas as f')
            ->where('f.estado', 'EMITIDA');

        if ($empresaId) {
            $facQ->where('f.empresa_id', $empresaId);
        }

        $fac = $facQ->selectRaw('
            COUNT(*) as total_emitidas,
            COALESCE(SUM(total), 0) as total_facturado,
            COALESCE(SUM(total_pagado), 0) as total_pagado_facturas,
            COALESCE(SUM(saldo), 0) as saldo_pendiente,
            SUM(CASE WHEN saldo > 0 THEN 1 ELSE 0 END) as facturas_con_saldo,
            SUM(CASE WHEN saldo <= 0 THEN 1 ELSE 0 END) as facturas_pagadas
        ')->first();

        $vrQ = DB::table('pagos as p')
            ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
            ->where('c.nombre_razon_social', 'MOSTRADOR');

        if ($empresaId) {
            $vrQ->where('p.empresa_id', $empresaId);
        }

        $vr = $vrQ->selectRaw('
            COUNT(*) as transacciones_ventas_rapidas,
            COALESCE(SUM(p.total_pagado), 0) as total_ventas_rapidas
        ')->first();

        $pagadoFacturas = (float)($fac->total_pagado_facturas ?? 0);
        $ventasRapidas  = (float)($vr->total_ventas_rapidas ?? 0);

        return [
            'total_emitidas'               => (int)($fac->total_emitidas ?? 0),
            'total_facturado'              => (float)($fac->total_facturado ?? 0),
            'total_pagado_facturas'        => $pagadoFacturas,
            'total_ventas_rapidas'         => $ventasRapidas,
            'total_recaudado'              => $pagadoFacturas + $ventasRapidas,
            'saldo_pendiente'              => (float)($fac->saldo_pendiente ?? 0),
            'facturas_con_saldo'           => (int)($fac->facturas_con_saldo ?? 0),
            'facturas_pagadas'             => (int)($fac->facturas_pagadas ?? 0),
            'transacciones_ventas_rapidas' => (int)($vr->transacciones_ventas_rapidas ?? 0),
        ];
    }

    public function flujoMensual(?int $empresaId, string $desde, string $hasta): array
{
    // Facturado por mes
    $qFac = DB::table('facturas as f')
        ->where('f.estado', 'EMITIDA')
        ->whereDate('f.fecha', '>=', $desde)
        ->whereDate('f.fecha', '<=', $hasta);
    if ($empresaId) $qFac->where('f.empresa_id', $empresaId);

    $facturado = $qFac
        ->selectRaw("DATE_FORMAT(f.fecha, '%Y-%m') as periodo, COALESCE(SUM(f.total), 0) as facturado")
        ->groupBy('periodo')
        ->get()->keyBy('periodo');

    // Aplicaciones a facturas por mes (no-MOSTRADOR)
    $qAplic = DB::table('pago_aplicaciones as pa')
        ->join('pagos as p',    'p.id',  '=', 'pa.pago_id')
        ->join('clientes as c', 'c.id',  '=', 'p.cliente_id')
        ->where('c.nombre_razon_social', '<>', 'MOSTRADOR')
        ->whereDate('p.fecha', '>=', $desde)
        ->whereDate('p.fecha', '<=', $hasta);
    if ($empresaId) $qAplic->where('p.empresa_id', $empresaId);

    $aplicaciones = $qAplic
        ->selectRaw("DATE_FORMAT(p.fecha, '%Y-%m') as periodo, COALESCE(SUM(pa.monto), 0) as ingresos_facturas")
        ->groupBy('periodo')
        ->get()->keyBy('periodo');

    // Ventas mostrador por mes
    $qMos = DB::table('pagos as p')
        ->join('clientes as c', 'c.id', '=', 'p.cliente_id')
        ->where('c.nombre_razon_social', 'MOSTRADOR')
        ->whereDate('p.fecha', '>=', $desde)
        ->whereDate('p.fecha', '<=', $hasta);
    if ($empresaId) $qMos->where('p.empresa_id', $empresaId);

    $mostrador = $qMos
        ->selectRaw("DATE_FORMAT(p.fecha, '%Y-%m') as periodo, COALESCE(SUM(p.total_pagado), 0) as ingresos_mostrador")
        ->groupBy('periodo')
        ->get()->keyBy('periodo');

    // Ingresos manuales por mes
    $qIng = DB::table('ingresos_manuales')
        ->whereDate('fecha', '>=', $desde)
        ->whereDate('fecha', '<=', $hasta);
    if ($empresaId) $qIng->where('empresa_id', $empresaId);

    $ingrManuales = $qIng
        ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as periodo, COALESCE(SUM(monto), 0) as ingresos_manuales")
        ->groupBy('periodo')
        ->get()->keyBy('periodo');

    // Egresos por mes
    $qEgr = DB::table('egresos')
        ->whereDate('fecha', '>=', $desde)
        ->whereDate('fecha', '<=', $hasta);
    if ($empresaId) $qEgr->where('empresa_id', $empresaId);

    $egresos = $qEgr
        ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as periodo, COALESCE(SUM(monto), 0) as egresos")
        ->groupBy('periodo')
        ->get()->keyBy('periodo');

    // Unir todos los períodos presentes en cualquier fuente
    $periodos = collect($facturado->keys())
        ->merge($aplicaciones->keys())
        ->merge($mostrador->keys())
        ->merge($ingrManuales->keys())
        ->merge($egresos->keys())
        ->unique()->sort()->values();

    return $periodos->map(function ($p) use ($facturado, $aplicaciones, $mostrador, $ingrManuales, $egresos) {
        $fac = (float)($facturado[$p]->facturado           ?? 0);
        $ifa = (float)($aplicaciones[$p]->ingresos_facturas  ?? 0);
        $imo = (float)($mostrador[$p]->ingresos_mostrador    ?? 0);
        $im  = (float)($ingrManuales[$p]->ingresos_manuales  ?? 0);
        $eg  = (float)($egresos[$p]->egresos                 ?? 0);
        $tc  = $ifa + $imo;

        return [
            'periodo'            => $p,          // "2025-03"
            'facturado'          => $fac,
            'ingresos_facturas'  => $ifa,
            'ingresos_mostrador' => $imo,
            'total_en_caja'      => $tc,
            'diferencia'         => $fac - $tc,  // positivo = saldo pendiente
            'ingresos_manuales'  => $im,
            'egresos'            => $eg,
            'balance_caja'       => $tc + $im - $eg,
        ];
    })->all();
}
}
