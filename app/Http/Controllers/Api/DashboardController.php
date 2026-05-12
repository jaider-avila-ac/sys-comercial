<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpresaResumen;
use App\Models\Factura;
use App\Models\IngresoPago;
use App\Models\IngresoMostrador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // GET /api/dashboard
    // Para EMPRESA_ADMIN y OPERATIVO — lee su empresa
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->empresa_id_ctx;
        
        // ✅ Usar directamente la tabla empresa_resumen (sin recalcular)
        $resumen = EmpresaResumen::find($empresaId);

        if (! $resumen) {
            return response()->json([
                'resumen'          => [],
                'ultimas_facturas' => [],
                'ultimos_pagos'    => [],
            ]);
        }

        // ✅ Incluir estado en las facturas
        $ultimasFacturas = Factura::where('empresa_id', $empresaId)
            ->whereIn('estado', ['EMITIDA', 'BORRADOR'])
            ->with('cliente')
            ->orderByDesc('fecha')
            ->limit(5)
            ->get(['id', 'numero', 'cliente_id', 'total', 'saldo', 'total_pagado', 'fecha', 'estado']);

        // ✅ Incluir el cliente en los pagos (a través de la factura relacionada)
        $ultimosPagos = IngresoPago::where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->with(['aplicaciones.factura.cliente'])
            ->orderByDesc('fecha')
            ->limit(5)
            ->get();

        // Formatear pagos para incluir cliente
        $pagosFormateados = $ultimosPagos->map(function ($pago) {
            $clienteNombre = null;
            foreach ($pago->aplicaciones as $aplicacion) {
                if ($aplicacion->factura && $aplicacion->factura->cliente) {
                    $clienteNombre = $aplicacion->factura->cliente->nombre_razon_social;
                    break;
                }
            }
            
            return [
                'id' => $pago->id,
                'numero' => $pago->numero,
                'fecha' => $pago->fecha,
                'monto' => $pago->monto,
                'forma_pago' => $pago->forma_pago,
                'cliente_nombre' => $clienteNombre,
            ];
        });

        $cuentasPorPagar = DB::table('compras')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('numero')
            ->whereIn('estado', ['PENDIENTE', 'PARCIAL'])
            ->sum('saldo_pendiente');

        $saldoPendiente = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', ['EMITIDA', 'BORRADOR'])
            ->sum('saldo');

        $today = now()->toDateString();

        $pagosFacHoy  = IngresoPago::where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereDate('fecha', $today)
            ->sum('monto');

        $mostradorHoy = IngresoMostrador::where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->whereDate('fecha', $today)
            ->sum('monto');

        $resumenArray = array_merge($resumen->toArray(), [
            'cuentas_por_pagar' => round((float) $cuentasPorPagar, 2),
            'saldo_pendiente'   => round((float) $saldoPendiente, 2),
            'ingresos_hoy'      => round((float) $pagosFacHoy + (float) $mostradorHoy, 2),
        ]);

        return response()->json([
            'resumen'          => $resumenArray,
            'ultimas_facturas' => $ultimasFacturas,
            'ultimos_pagos'    => $pagosFormateados,
        ]);
    }

    // GET /api/dashboard/empresas
    // Solo SUPER_ADMIN — resumen de todas las empresas
    public function todasLasEmpresas(): JsonResponse
    {
        $resumenes = EmpresaResumen::with('empresa')
            ->orderByDesc('ultima_actividad')
            ->get();

        return response()->json($resumenes);
    }
}