<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{
    private function resolveEmpresaId(Request $request): ?int
    {
        $u = $request->user();
        if ($u->rol === 'SUPER_ADMIN') {
            $eid = (int)($request->query('empresa_id') ?? 0);
            return $eid > 0 ? $eid : null;
        }
        if (!$u->empresa_id) abort(403, 'Sin empresa');
        return (int)$u->empresa_id;
    }

    private function validateRango(Request $request): array
    {
        return $request->validate([
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ]);
    }

    // =========================================================
    // 1) VENTAS (líneas) por fecha de FACTURA
    // =========================================================
    public function ventasLineas(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $r = $this->validateRango($request);

        $q = DB::table('factura_lineas as fl')
            ->join('facturas as f', 'f.id', '=', 'fl.factura_id')
            ->leftJoin('items as it', 'it.id', '=', 'fl.item_id')
            ->select([
                'f.id as factura_id',
                'f.numero as factura_numero',
                'f.fecha as factura_fecha',
                'f.estado as factura_estado',
                'f.total as factura_total',
                'f.total_iva as factura_total_iva',
                'f.total_pagado as factura_total_pagado',
                'f.saldo as factura_saldo',
                'fl.id as linea_id',
                'fl.item_id',
                DB::raw("COALESCE(it.nombre, '') as item_nombre"),
                'fl.descripcion_manual',
                'fl.cantidad',
                'fl.valor_unitario',
                'fl.descuento',
                'fl.iva_pct',
                'fl.iva_valor',
                'fl.total_linea',
            ])
            ->where('f.estado', 'EMITIDA')
            ->whereDate('f.fecha', '>=', $r['desde'])
            ->whereDate('f.fecha', '<=', $r['hasta']);

        if ($empresaId) $q->where('f.empresa_id', $empresaId);
        $q->orderBy('f.fecha')->orderBy('f.numero')->orderBy('fl.id');

        return response()->json($q->paginate(100));
    }

    // =========================================================
    // 2) RESUMEN FACTURADO por rango (fecha FACTURA)
    // =========================================================
    public function ventasResumen(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $r = $this->validateRango($request);

        $q = DB::table('facturas as f')
            ->where('f.estado', 'EMITIDA')
            ->whereDate('f.fecha', '>=', $r['desde'])
            ->whereDate('f.fecha', '<=', $r['hasta']);

        if ($empresaId) $q->where('f.empresa_id', $empresaId);

        $row = $q->selectRaw('
            COUNT(*) as facturas,
            SUM(f.subtotal)         as subtotal,
            SUM(f.total_descuentos) as total_descuentos,
            SUM(f.total_iva)        as total_iva,
            SUM(f.total)            as total_facturado,
            SUM(f.total_pagado)     as total_pagado_actual,
            SUM(f.saldo)            as saldo_actual
        ')->first();

        return response()->json([
            'desde'               => $r['desde'],
            'hasta'               => $r['hasta'],
            'facturas'            => (int)($row->facturas ?? 0),
            'subtotal'            => (float)($row->subtotal ?? 0),
            'total_descuentos'    => (float)($row->total_descuentos ?? 0),
            'total_iva'           => (float)($row->total_iva ?? 0),
            'total_facturado'     => (float)($row->total_facturado ?? 0),
            'total_pagado_actual' => (float)($row->total_pagado_actual ?? 0),
            'saldo_actual'        => (float)($row->saldo_actual ?? 0),
        ]);
    }

    // =========================================================
    // 3) RESUMEN RECAUDOS + ingresos manuales + egresos
    // =========================================================
    public function recaudosResumen(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $r = $this->validateRango($request);

        $q = DB::table('pagos as p')
            ->leftJoin('pago_aplicaciones as pa', 'pa.pago_id', '=', 'p.id')
            ->whereDate('p.fecha', '>=', $r['desde'])
            ->whereDate('p.fecha', '<=', $r['hasta']);
        if ($empresaId) $q->where('p.empresa_id', $empresaId);
        $row = $q->selectRaw('
            COUNT(DISTINCT p.id) as pagos,
            SUM(p.total_pagado)  as total_caja,
            SUM(pa.monto)        as total_aplicado
        ')->first();

        $qIng = DB::table('ingresos_manuales')
            ->whereDate('fecha', '>=', $r['desde'])
            ->whereDate('fecha', '<=', $r['hasta']);
        if ($empresaId) $qIng->where('empresa_id', $empresaId);
        $totalIngresosManuales = (float) $qIng->sum('monto');

        $qEgr = DB::table('egresos')
            ->whereDate('fecha', '>=', $r['desde'])
            ->whereDate('fecha', '<=', $r['hasta']);
        if ($empresaId) $qEgr->where('empresa_id', $empresaId);
        $totalEgresos = (float) $qEgr->sum('monto');

        $totalCaja   = (float)($row->total_caja ?? 0);
        $balanceReal = $totalCaja + $totalIngresosManuales - $totalEgresos;

        return response()->json([
            'desde'                   => $r['desde'],
            'hasta'                   => $r['hasta'],
            'pagos'                   => (int)($row->pagos ?? 0),
            'total_caja'              => $totalCaja,
            'total_aplicado'          => (float)($row->total_aplicado ?? 0),
            'total_ingresos_manuales' => $totalIngresosManuales,
            'total_egresos'           => $totalEgresos,
            'balance_real'            => $balanceReal,
        ]);
    }

    // =========================================================
    // 4) SALDO AL CIERRE
    // =========================================================
    public function saldoAlCierre(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);

        $data = $request->validate([
            'desde'  => ['required', 'date'],
            'hasta'  => ['required', 'date', 'after_or_equal:desde'],
            'cierre' => ['required', 'date'],
        ]);

        $desde  = $data['desde'];
        $hasta  = $data['hasta'];
        $cierre = $data['cierre'];

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

        if ($empresaId) $q->where('f.empresa_id', $empresaId);

        $row = $q->selectRaw('
            COUNT(*) as facturas,
            SUM(f.total) as total_facturado,
            SUM(COALESCE(ap.aplicado_hasta_cierre, 0)) as total_aplicado_hasta_cierre,
            SUM(GREATEST(0, f.total - COALESCE(ap.aplicado_hasta_cierre, 0))) as saldo_al_cierre
        ')->first();

        return response()->json([
            'desde'                       => $desde,
            'hasta'                       => $hasta,
            'cierre'                      => $cierre,
            'facturas'                    => (int)($row->facturas ?? 0),
            'total_facturado'             => (float)($row->total_facturado ?? 0),
            'total_aplicado_hasta_cierre' => (float)($row->total_aplicado_hasta_cierre ?? 0),
            'saldo_al_cierre'             => (float)($row->saldo_al_cierre ?? 0),
        ]);
    }

    // =========================================================
    // 5) FLUJO MENSUAL + ingresos manuales + egresos
    // =========================================================
    public function flujoMensual(Request $request)
    {
        $empresaId = $this->resolveEmpresaId($request);
        $r = $this->validateRango($request);

        $fac = DB::table('facturas as f')
            ->where('f.estado', 'EMITIDA')
            ->whereDate('f.fecha', '>=', $r['desde'])
            ->whereDate('f.fecha', '<=', $r['hasta'])
            ->when($empresaId, fn($qq) => $qq->where('f.empresa_id', $empresaId))
            ->selectRaw("DATE_FORMAT(f.fecha, '%Y-%m') as periodo, SUM(f.total) as facturado")
            ->groupBy('periodo')->pluck('facturado', 'periodo');

        $pag = DB::table('pagos as p')
            ->leftJoin('pago_aplicaciones as pa', 'pa.pago_id', '=', 'p.id')
            ->whereDate('p.fecha', '>=', $r['desde'])
            ->whereDate('p.fecha', '<=', $r['hasta'])
            ->when($empresaId, fn($qq) => $qq->where('p.empresa_id', $empresaId))
            ->selectRaw("DATE_FORMAT(p.fecha, '%Y-%m') as periodo, SUM(pa.monto) as aplicado")
            ->groupBy('periodo')->pluck('aplicado', 'periodo');

        $ing = DB::table('ingresos_manuales')
            ->whereDate('fecha', '>=', $r['desde'])
            ->whereDate('fecha', '<=', $r['hasta'])
            ->when($empresaId, fn($qq) => $qq->where('empresa_id', $empresaId))
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as periodo, SUM(monto) as ingreso")
            ->groupBy('periodo')->pluck('ingreso', 'periodo');

        $egr = DB::table('egresos')
            ->whereDate('fecha', '>=', $r['desde'])
            ->whereDate('fecha', '<=', $r['hasta'])
            ->when($empresaId, fn($qq) => $qq->where('empresa_id', $empresaId))
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m') as periodo, SUM(monto) as egreso")
            ->groupBy('periodo')->pluck('egreso', 'periodo');

        $periodos = collect(array_unique(array_merge(
            $fac->keys()->all(), $pag->keys()->all(),
            $ing->keys()->all(), $egr->keys()->all()
        )))->sort()->values();

        $out = $periodos->map(fn($per) => [
            'periodo'           => $per,
            'facturado'         => (float)($fac[$per] ?? 0),
            'aplicado'          => (float)($pag[$per] ?? 0),
            'diferencia'        => (float)($fac[$per] ?? 0) - (float)($pag[$per] ?? 0),
            'ingresos_manuales' => (float)($ing[$per] ?? 0),
            'egresos'           => (float)($egr[$per] ?? 0),
            'balance_caja'      => (float)($pag[$per] ?? 0) + (float)($ing[$per] ?? 0) - (float)($egr[$per] ?? 0),
        ]);

        return response()->json([
            'desde' => $r['desde'],
            'hasta' => $r['hasta'],
            'data'  => $out,
        ]);
    }
}