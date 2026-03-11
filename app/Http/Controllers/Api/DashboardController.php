<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ResolvesEmpresa;
use App\Services\IndicadoresComercialesService;
use App\Models\Cliente;
use App\Models\Item;
use App\Models\Cotizacion;
use App\Models\Factura;
use App\Models\Pago;
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

        // ── Contadores generales ──────────────────────────────
        $totalClientes  = Cliente::where('empresa_id', $empresaId)->count();
        $totalItems     = Item::where('empresa_id', $empresaId)->count();
        $cotizActivas   = Cotizacion::where('empresa_id', $empresaId)
                            ->whereIn('estado', ['BORRADOR', 'EMITIDA', 'VIGENTE'])
                            ->count();
        $factBorrador   = Factura::where('empresa_id', $empresaId)
                            ->where('estado', 'BORRADOR')
                            ->count();

        // ── Últimas 8 facturas ────────────────────────────────
        $ultimasFacturas = Factura::with('cliente:id,nombre_razon_social')
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn($f) => [
                'id'      => $f->id,
                'numero'  => $f->numero,
                'estado'  => $f->estado,
                'fecha'   => $f->fecha?->toDateString(),
                'total'   => (float) $f->total,
                'saldo'   => (float) $f->saldo,
                'cliente' => $f->cliente
                    ? ['nombre_razon_social' => $f->cliente->nombre_razon_social]
                    : null,
            ]);

        // ── Facturas con saldo pendiente (máx. 10) ────────────
        $facturasPendientes = Factura::with('cliente:id,nombre_razon_social')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'EMITIDA')
            ->where('saldo', '>', 0)
            ->orderByDesc('saldo')
            ->limit(5)
            ->get()
            ->map(fn($f) => [
                'id'      => $f->id,
                'numero'  => $f->numero,
                'fecha'   => $f->fecha?->toDateString(),
                'total'   => (float) $f->total,
                'saldo'   => (float) $f->saldo,
                'cliente' => $f->cliente
                    ? ['nombre_razon_social' => $f->cliente->nombre_razon_social]
                    : null,
            ]);

        // ── Últimos 8 pagos ───────────────────────────────────
        $ultimosPagos = Pago::with('cliente:id,nombre_razon_social')
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'numero_recibo'  => $p->numero_recibo,
                'fecha'          => $p->fecha?->toDateString(),
                'forma_pago'     => $p->forma_pago,
                'total_pagado'   => (float) $p->total_pagado,
                'total_aplicado' => (float) $p->total_pagado, // alias consistente
                'cliente'        => $p->cliente
                    ? ['nombre_razon_social' => $p->cliente->nombre_razon_social]
                    : null,
            ]);

        return response()->json([
            'kpi' => [
                'total_clientes'       => $totalClientes,
                'total_items'          => $totalItems,
                'cotizaciones_activas' => $cotizActivas,
                'facturas_borrador'    => $factBorrador,
                'total_emitidas'       => $kpi['total_emitidas'],
                'total_facturado'      => $kpi['total_facturado'],
                'total_recaudado'      => $kpi['total_recaudado'],
                'total_ventas_rapidas' => $kpi['total_ventas_rapidas'],
                'saldo_pendiente'      => $kpi['saldo_pendiente'],
                'facturas_con_saldo'   => $kpi['facturas_con_saldo'],
                'facturas_pagadas'     => $kpi['facturas_pagadas'],
            ],
            'ultimas_facturas'    => $ultimasFacturas,
            'facturas_pendientes' => $facturasPendientes,
            'ultimos_pagos'       => $ultimosPagos,
        ]);
    }
}