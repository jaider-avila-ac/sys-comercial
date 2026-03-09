<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Egreso;
use App\Http\Controllers\Api\Concerns\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Pago;

class EgresoController extends Controller
{
    use AuditLog;

    // ── GET /api/egresos ──────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user      = $request->user();
        $empresaId = $user->empresa_id;

        $q = Egreso::where('empresa_id', $empresaId)
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('desde')) {
            $q->whereDate('fecha', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $q->whereDate('fecha', '<=', $request->input('hasta'));
        }

        return response()->json($q->get()->map(fn($e) => $this->format($e)));
    }

    // ── POST /api/egresos ─────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'descripcion' => 'required|string|max:255',
            'monto'       => 'required|numeric|min:0.01',
            'fecha'       => 'required|date',
            'archivo'     => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        $disponible = $this->calcularBalanceDisponible($user->empresa_id);
        if ((float) $validated['monto'] > $disponible) {
            return response()->json([
                'message' => "Saldo insuficiente. Balance disponible: $" . number_format($disponible, 2),
            ], 422);
        }

        $archivoPath   = null;
        $archivoMime   = null;
        $archivoNombre = null;

        if ($request->hasFile('archivo')) {
            $file          = $request->file('archivo');
            $archivoNombre = $file->getClientOriginalName();
            $archivoMime   = $file->getMimeType();
            $archivoPath   = $file->store("egresos/{$user->empresa_id}", 'public');
        }

        $egreso = Egreso::create([
            'empresa_id'     => $user->empresa_id,
            'usuario_id'     => $user->id,
            'descripcion'    => $validated['descripcion'],
            'monto'          => $validated['monto'],
            'fecha'          => $validated['fecha'],
            'archivo_path'   => $archivoPath,
            'archivo_mime'   => $archivoMime,
            'archivo_nombre' => $archivoNombre,
        ]);

        $this->audit(
            $request, 'egresos', 'CREAR', $egreso->id,
            "Egreso #{$egreso->id} — {$egreso->descripcion} por \${$egreso->monto}",
            $user->empresa_id
        );

        return response()->json($this->format($egreso), 201);
    }

    // ── GET /api/egresos/{id} ─────────────────────────────────
    public function show(Request $request, int $id): JsonResponse
    {
        $egreso = $this->findOrFail($request->user()->empresa_id, $id);
        return response()->json($this->format($egreso));
    }

    // ── POST /api/egresos/{id} (update) ───────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $egreso = $this->findOrFail($user->empresa_id, $id);

        $validated = $request->validate([
            'descripcion' => 'required|string|max:255',
            'monto'       => 'required|numeric|min:0.01',
            'fecha'       => 'required|date',
            'archivo'     => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
        ]);

        $disponible = $this->calcularBalanceDisponible($user->empresa_id, (float) $egreso->monto);
        if ((float) $validated['monto'] > $disponible) {
            return response()->json([
                'message' => "Saldo insuficiente. Balance disponible: $" . number_format($disponible, 2),
            ], 422);
        }

        if ($request->hasFile('archivo')) {
            if ($egreso->archivo_path) {
                Storage::disk('public')->delete($egreso->archivo_path);
            }
            $file                   = $request->file('archivo');
            $egreso->archivo_path   = $file->store("egresos/{$user->empresa_id}", 'public');
            $egreso->archivo_mime   = $file->getMimeType();
            $egreso->archivo_nombre = $file->getClientOriginalName();
        }

        $egreso->descripcion = $validated['descripcion'];
        $egreso->monto       = $validated['monto'];
        $egreso->fecha       = $validated['fecha'];
        $egreso->save();

        $this->audit(
            $request, 'egresos', 'EDITAR', $egreso->id,
            "Egreso #{$egreso->id} actualizado",
            $user->empresa_id
        );

        return response()->json($this->format($egreso));
    }

    // ── DELETE /api/egresos/{id} ──────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $egreso = $this->findOrFail($user->empresa_id, $id);

        if ($egreso->archivo_path) {
            Storage::disk('public')->delete($egreso->archivo_path);
        }

        $egreso->delete();

        $this->audit(
            $request, 'egresos', 'ELIMINAR', $id,
            "Egreso #{$id} eliminado",
            $user->empresa_id
        );

        return response()->json(['message' => 'Egreso eliminado.']);
    }

    // ── helpers ───────────────────────────────────────────────
    private function findOrFail(int $empresaId, int $id): Egreso
    {
        return Egreso::where('empresa_id', $empresaId)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function format(Egreso $e): array
    {
        return [
            'id'             => $e->id,
            'descripcion'    => $e->descripcion,
            'monto'          => (float) $e->monto,
            'fecha'          => $e->fecha?->toDateString(),
            'archivo_url'    => $e->archivo_path ? asset('storage/' . $e->archivo_path) : null,
            'archivo_nombre' => $e->archivo_nombre,
            'archivo_mime'   => $e->archivo_mime,
            'usuario_id'     => $e->usuario_id,
            'created_at'     => $e->created_at?->toDateTimeString(),
        ];
    }

    private function calcularBalanceDisponible(int $empresaId, float $montoExcluir = 0): float
    {
        $totalPagos    = (float) Pago::where('empresa_id', $empresaId)->sum('total_pagado');
        $totalManuales = (float) \App\Models\IngresoManual::where('empresa_id', $empresaId)->sum('monto');
        $totalEgresos  = (float) Egreso::where('empresa_id', $empresaId)->sum('monto');

        return ($totalPagos + $totalManuales) - ($totalEgresos - $montoExcluir);
    }
}