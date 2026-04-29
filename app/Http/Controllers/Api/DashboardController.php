<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpresaResumen;
use App\Models\Factura;
use App\Models\IngresoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            return response()->json(['message' => 'Sin datos aún. El sistema está generando el resumen.'], 404);
        }

        // ✅ Incluir estado en las facturas
        $ultimasFacturas = Factura::where('empresa_id', $empresaId)
            ->where('estado', 'EMITIDA')
            ->with('cliente')
            ->orderByDesc('fecha')
            ->limit(5)
            ->get(['id', 'numero', 'cliente_id', 'total', 'saldo', 'fecha', 'estado']);

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

        return response()->json([
            'resumen'          => $resumen,
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