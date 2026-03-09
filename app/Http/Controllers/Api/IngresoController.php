<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\IngresoManual;
use App\Models\Pago;
use App\Models\Egreso;
use App\Models\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngresoController extends Controller
{
    // ── GET /api/ingresos/resumen ─────────────────────────────
    public function resumen(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;
        $desde     = $request->input('desde');
        $hasta     = $request->input('hasta');

        $qPagos = Pago::where('empresa_id', $empresaId);
        if ($desde) $qPagos->whereDate('fecha', '>=', $desde);
        if ($hasta) $qPagos->whereDate('fecha', '<=', $hasta);
        $totalPagos = (float) $qPagos->sum('total_pagado');

        $qManuales = IngresoManual::where('empresa_id', $empresaId);
        if ($desde) $qManuales->whereDate('fecha', '>=', $desde);
        if ($hasta) $qManuales->whereDate('fecha', '<=', $hasta);
        $totalManuales = (float) $qManuales->sum('monto');

        $qEgresos = Egreso::where('empresa_id', $empresaId);
        if ($desde) $qEgresos->whereDate('fecha', '>=', $desde);
        if ($hasta) $qEgresos->whereDate('fecha', '<=', $hasta);
        $totalEgresos = (float) $qEgresos->sum('monto');

        $totalIngresos = $totalPagos + $totalManuales;
        $balance       = $totalIngresos - $totalEgresos;

        return response()->json([
            'ingresos_pagos'    => $totalPagos,
            'ingresos_manuales' => $totalManuales,
            'total_ingresos'    => $totalIngresos,
            'total_egresos'     => $totalEgresos,
            'balance'           => $balance,
        ]);
    }

    // ── GET /api/ingresos/pagos ───────────────────────────────
    // Lista todos los pagos para mostrarlos en el módulo finanzas
    public function pagos(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        $q = Pago::where('empresa_id', $empresaId)
            ->with('cliente:id,nombre_razon_social')
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('desde')) $q->whereDate('fecha', '>=', $request->input('desde'));
        if ($request->filled('hasta')) $q->whereDate('fecha', '<=', $request->input('hasta'));

        $list = $q->get()->map(fn($p) => [
            'id'                  => $p->id,
            'numero_recibo'       => $p->numero_recibo,
            'fecha'               => $p->fecha?->toDateString(),
            'cliente'             => $p->cliente?->nombre_razon_social ?? '—',
            'forma_pago'          => $p->forma_pago,
            'referencia'          => $p->referencia,
            'notas'               => $p->notas,
            'total_pagado'        => (float) $p->total_pagado,
        ]);

        return response()->json($list);
    }

    // ── GET /api/ingresos/manuales ────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        $q = IngresoManual::where('empresa_id', $empresaId)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('desde')) $q->whereDate('fecha', '>=', $request->input('desde'));
        if ($request->filled('hasta')) $q->whereDate('fecha', '<=', $request->input('hasta'));

        return response()->json($q->get()->map(fn($i) => $this->format($i)));
    }

    // ── POST /api/ingresos/manuales ───────────────────────────
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'descripcion' => 'required|string|max:255',
            'monto'       => 'required|numeric|min:0.01',
            'fecha'       => 'required|date',
            'notas'       => 'nullable|string|max:255',
        ]);

        $ingreso = IngresoManual::create([
            'empresa_id'  => $user->empresa_id,
            'usuario_id'  => $user->id,
            'descripcion' => $validated['descripcion'],
            'monto'       => $validated['monto'],
            'fecha'       => $validated['fecha'],
            'notas'       => $validated['notas'] ?? null,
        ]);

        Auditoria::create([
            'empresa_id'  => $user->empresa_id,
            'usuario_id'  => $user->id,
            'entidad'     => 'ingresos_manuales',
            'accion'      => 'CREAR',
            'entidad_id'  => $ingreso->id,
            'descripcion' => "Ingreso manual #{$ingreso->id} — {$ingreso->descripcion} por \${$ingreso->monto}",
            'ip'          => $request->ip(),
            'ocurrido_en' => now(),
        ]);

        return response()->json($this->format($ingreso), 201);
    }

    // ── PUT /api/ingresos/manuales/{id} ───────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $ingreso = $this->findOrFail($user->empresa_id, $id);

        $validated = $request->validate([
            'descripcion' => 'required|string|max:255',
            'monto'       => 'required|numeric|min:0.01',
            'fecha'       => 'required|date',
            'notas'       => 'nullable|string|max:255',
        ]);

        $ingreso->update($validated);

        Auditoria::create([
            'empresa_id'  => $user->empresa_id,
            'usuario_id'  => $user->id,
            'entidad'     => 'ingresos_manuales',
            'accion'      => 'EDITAR',
            'entidad_id'  => $ingreso->id,
            'descripcion' => "Ingreso manual #{$ingreso->id} actualizado",
            'ip'          => $request->ip(),
            'ocurrido_en' => now(),
        ]);

        return response()->json($this->format($ingreso));
    }

    // ── DELETE /api/ingresos/manuales/{id} ────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user    = $request->user();
        $ingreso = $this->findOrFail($user->empresa_id, $id);
        $ingreso->delete();

        Auditoria::create([
            'empresa_id'  => $user->empresa_id,
            'usuario_id'  => $user->id,
            'entidad'     => 'ingresos_manuales',
            'accion'      => 'ELIMINAR',
            'entidad_id'  => $id,
            'descripcion' => "Ingreso manual #{$id} eliminado",
            'ip'          => $request->ip(),
            'ocurrido_en' => now(),
        ]);

        return response()->json(['message' => 'Ingreso eliminado.']);
    }

    private function findOrFail(int $empresaId, int $id): IngresoManual
    {
        return IngresoManual::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function format(IngresoManual $i): array
    {
        return [
            'id'          => $i->id,
            'descripcion' => $i->descripcion,
            'monto'       => (float) $i->monto,
            'fecha'       => $i->fecha?->toDateString(),
            'notas'       => $i->notas,
            'usuario_id'  => $i->usuario_id,
            'created_at'  => $i->created_at?->toDateTimeString(),
        ];
    }
}