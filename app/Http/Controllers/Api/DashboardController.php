<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Cliente;
use App\Models\Item;
use App\Models\Cotizacion;
use Illuminate\Http\Request;

/**
 * GET /api/dashboard
 * Un solo endpoint eficiente para el panel principal.
 * Agrega datos por empresa con queries simples sin paginacion.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();

        $empresaId = null;
        if ($u->rol !== 'SUPER_ADMIN') {
            if (!$u->empresa_id) {
                return response()->json(['message' => 'Sin empresa'], 403);
            }
            $empresaId = (int)$u->empresa_id;
        } else {
            $eid = $request->query('empresa_id');
            $empresaId = $eid ? (int)$eid : null;
        }

        // ── KPIs catalogo ──────────────────────────────────────────────────
        $totalClientes = Cliente::query()
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->count();

        $totalItems = Item::query()
            ->where('is_activo', 1)
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->count();

        $cotizacionesActivas = Cotizacion::query()
            ->whereIn('estado', ['BORRADOR', 'EMITIDA'])
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->count();

        // ── KPIs facturacion ───────────────────────────────────────────────
        $kpiFacturas = Factura::query()
            ->where('estado', 'EMITIDA')
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->selectRaw('
                COUNT(*)                                     AS total_emitidas,
                COALESCE(SUM(total), 0)                      AS total_facturado,
                COALESCE(SUM(total_pagado), 0)               AS total_recaudado,
                COALESCE(SUM(saldo), 0)                      AS saldo_pendiente,
                SUM(CASE WHEN saldo > 0  THEN 1 ELSE 0 END) AS facturas_con_saldo,
                SUM(CASE WHEN saldo <= 0 THEN 1 ELSE 0 END) AS facturas_pagadas
            ')->first();

        $facturasBorrador = Factura::query()
            ->where('estado', 'BORRADOR')
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->count();

        // ── Ultimas 6 facturas ─────────────────────────────────────────────
        $ultimasFacturas = Factura::query()
            ->select('id', 'numero', 'estado', 'fecha', 'total', 'saldo', 'cliente_id')
            ->with('cliente:id,nombre_razon_social')
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        // ── Top 5 facturas pendientes (mayor saldo) ────────────────────────
        $facturasPendientes = Factura::query()
            ->select('id', 'numero', 'fecha', 'total', 'total_pagado', 'saldo', 'cliente_id')
            ->with('cliente:id,nombre_razon_social')
            ->where('estado', 'EMITIDA')
            ->where('saldo', '>', 0)
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('saldo')
            ->limit(5)
            ->get();

        // ── Ultimos 5 pagos ────────────────────────────────────────────────
        // IMPORTANTE: Para UI/KPIs interesa lo PAGADO (APLICADO a facturas),
        // no el efectivo entregado si hubo cambio.
        $ultimosPagos = Pago::query()
            ->with('cliente:id,nombre_razon_social')
            ->withSum('aplicaciones as total_aplicado', 'monto')
            ->when($empresaId, fn($q) => $q->where('empresa_id', $empresaId))
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'numero_recibo', 'fecha', 'forma_pago', 'cliente_id']);

        return response()->json([
            'kpi' => [
                'total_clientes'       => $totalClientes,
                'total_items'          => $totalItems,
                'cotizaciones_activas' => $cotizacionesActivas,
                'facturas_borrador'    => $facturasBorrador,
                'total_emitidas'       => (int)($kpiFacturas->total_emitidas    ?? 0),
                'total_facturado'      => (float)($kpiFacturas->total_facturado ?? 0),
                'total_recaudado'      => (float)($kpiFacturas->total_recaudado ?? 0),
                'saldo_pendiente'      => (float)($kpiFacturas->saldo_pendiente ?? 0),
                'facturas_con_saldo'   => (int)($kpiFacturas->facturas_con_saldo ?? 0),
                'facturas_pagadas'     => (int)($kpiFacturas->facturas_pagadas  ?? 0),
            ],
            'ultimas_facturas'    => $ultimasFacturas,
            'facturas_pendientes' => $facturasPendientes,
            'ultimos_pagos'       => $ultimosPagos,
        ]);
    }
}