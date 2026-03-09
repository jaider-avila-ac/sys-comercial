<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\Pago;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    /**
     * GET /api/pagos
     * Lista todos los pagos con filtros.
     * Query params: search (numero_recibo), cliente_id, forma_pago,
     *               fecha_desde, fecha_hasta, page
     */
    public function index(Request $request)
    {
        $u = $request->user();

        $q           = trim((string)$request->query('search', ''));
        $clienteId   = $request->query('cliente_id');
        $formaPago   = $request->query('forma_pago');
        $fechaDesde  = $request->query('fecha_desde');
        $fechaHasta  = $request->query('fecha_hasta');

        $query = Pago::query()
            ->with('cliente:id,nombre_razon_social', 'aplicaciones.factura:id,numero')
            // ✅ PARA MOSTRAR LO QUE REALMENTE PAGÓ (APLICADO), NO LO RECIBIDO:
            ->withSum('aplicaciones as total_aplicado', 'monto');

        // Scope empresa
        if ($u->rol !== 'SUPER_ADMIN') {
            if (!$u->empresa_id) {
                return response()->json(['message' => 'Sin empresa'], 403);
            }
            $query->where('empresa_id', $u->empresa_id);
        } else {
            $eid = $request->query('empresa_id');
            if ($eid) $query->where('empresa_id', (int)$eid);
        }

        if ($q)          $query->where('numero_recibo', 'like', "%{$q}%");
        if ($clienteId)  $query->where('cliente_id', (int)$clienteId);
        if ($formaPago)  $query->where('forma_pago', $formaPago);
        if ($fechaDesde) $query->whereDate('fecha', '>=', $fechaDesde);
        if ($fechaHasta) $query->whereDate('fecha', '<=', $fechaHasta);

        return response()->json(
            $query->orderByDesc('id')->paginate(20)
        );
    }

    /**
     * GET /api/pagos/resumen
     * KPIs del módulo: total recaudado, saldo pendiente, facturas emitidas/pagadas.
     */
    public function resumen(Request $request)
    {
        $u = $request->user();

        // Scope empresa
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

        $facQ = Factura::query()->where('estado', 'EMITIDA');
        $pagQ = Pago::query();

        if ($empresaId) {
            $facQ->where('empresa_id', $empresaId);
            $pagQ->where('empresa_id', $empresaId);
        }

        $totales = $facQ->selectRaw('
            COUNT(*) as total_facturas,
            SUM(total) as total_facturado,
            SUM(total_pagado) as total_recaudado,
            SUM(saldo) as saldo_pendiente,
            SUM(CASE WHEN saldo <= 0 THEN 1 ELSE 0 END) as facturas_pagadas,
            SUM(CASE WHEN saldo >  0 THEN 1 ELSE 0 END) as facturas_con_saldo
        ')->first();

        return response()->json([
            'total_facturas'     => (int)($totales->total_facturas ?? 0),
            'total_facturado'    => (float)($totales->total_facturado ?? 0),
            'total_recaudado'    => (float)($totales->total_recaudado ?? 0),
            'saldo_pendiente'    => (float)($totales->saldo_pendiente ?? 0),
            'facturas_pagadas'   => (int)($totales->facturas_pagadas ?? 0),
            'facturas_con_saldo' => (int)($totales->facturas_con_saldo ?? 0),
        ]);
    }

    /**
     * GET /api/pagos/facturas-pendientes
     * Facturas EMITIDAS con saldo > 0 para el módulo "Cobrar".
     * Query params: search (numero o cliente), cliente_id, page
     */
    public function facturasPendientes(Request $request)
    {
        $u = $request->user();

        $q         = trim((string)$request->query('search', ''));
        $clienteId = $request->query('cliente_id');

        $query = Factura::query()
            ->with('cliente:id,nombre_razon_social')
            ->where('estado', 'EMITIDA')
            ->where('saldo', '>', 0);

        if ($u->rol !== 'SUPER_ADMIN') {
            if (!$u->empresa_id) {
                return response()->json(['message' => 'Sin empresa'], 403);
            }
            $query->where('empresa_id', $u->empresa_id);
        } else {
            $eid = $request->query('empresa_id');
            if ($eid) $query->where('empresa_id', (int)$eid);
        }

        if ($clienteId) $query->where('cliente_id', (int)$clienteId);

        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('numero', 'like', "%{$q}%")
                  ->orWhereHas('cliente', fn($c) =>
                      $c->where('nombre_razon_social', 'like', "%{$q}%")
                  );
            });
        }

        return response()->json(
            $query->orderByDesc('id')->paginate(15)
        );
    }
}