<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\EgresoCompra;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\PagoAplicacion;
use App\Services\ResumenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(private readonly ResumenService $resumenService) {}

    /**
     * POST /api/admin/recalcular-todo
     * Solo SUPER_ADMIN. Recalcula saldos de facturas, compras y empresa_resumen.
     */
    public function recalcularTodo(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if ($usuario->rol !== 'SUPER_ADMIN') {
            abort(403, 'Solo SUPER_ADMIN puede ejecutar esta acción.');
        }

        $stats = ['facturas' => 0, 'compras' => 0, 'empresas' => 0];

        DB::transaction(function () use (&$stats) {

            // ── Facturas EMITIDAS ──────────────────────────────────────────────
            Factura::where('estado', 'EMITIDA')->each(function (Factura $factura) use (&$stats) {
                $totalPagado = PagoAplicacion::where('factura_id', $factura->id)
                    ->whereHas('ingresoPago', fn($q) => $q->where('estado', 'ACTIVO'))
                    ->sum('monto');

                $totalPagado = round((float) $totalPagado, 2);
                $saldo       = round(max(0, (float) $factura->total - $totalPagado), 2);

                $factura->updateQuietly(['total_pagado' => $totalPagado, 'saldo' => $saldo]);
                $stats['facturas']++;
            });

            // ── Compras confirmadas (no anuladas) ──────────────────────────────
            Compra::whereNotNull('numero')
                ->whereNotIn('estado', ['ANULADA'])
                ->each(function (Compra $compra) use (&$stats) {
                    $pagado = EgresoCompra::where('compra_id', $compra->id)
                        ->where('estado', 'ACTIVO')
                        ->sum('monto');

                    $pagado      = round((float) $pagado, 2);
                    $nuevoSaldo  = round(max(0, (float) $compra->total - $pagado), 2);
                    $nuevoEstado = $nuevoSaldo <= 0
                        ? 'PAGADA'
                        : ($pagado > 0 ? 'PARCIAL' : 'PENDIENTE');

                    $compra->updateQuietly(['saldo_pendiente' => $nuevoSaldo, 'estado' => $nuevoEstado]);
                    $stats['compras']++;
                });
        });

        // ── empresa_resumen (fuera de la transacción de datos para lectura consistente) ──
        Empresa::where('is_activa', true)->each(function (Empresa $empresa) use (&$stats) {
            $this->resumenService->recalcular($empresa->id);
            $stats['empresas']++;
        });

        return response()->json([
            'message' => 'Recalculación completada.',
            'stats'   => $stats,
        ]);
    }
}
