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
        $resumen   = EmpresaResumen::find($empresaId);

        if (! $resumen) {
            return response()->json(['message' => 'Sin datos aún.'], 404);
        }

        // Actividad reciente — estas sí se consultan en tiempo real
        // pero son solo los últimos 5 registros, muy baratos
        $ultimasFacturas = Factura::where('empresa_id', $empresaId)
            ->where('estado', 'EMITIDA')
            ->with('cliente')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'numero', 'cliente_id', 'total', 'saldo', 'fecha']);

        $ultimosPagos = IngresoPago::where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'numero', 'monto', 'forma_pago', 'fecha']);

        return response()->json([
            'resumen'          => $resumen,
            'ultimas_facturas' => $ultimasFacturas,
            'ultimos_pagos'    => $ultimosPagos,
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