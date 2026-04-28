<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\EgresoCompra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EgresoCompraRepository
{
    public function paginate(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        $desde  = $filters['desde']  ?? null;
        $hasta  = $filters['hasta']  ?? null;

        return EgresoCompra::where('empresa_id', $empresaId)
            ->with(['usuario', 'compra'])
            ->when($search, fn($q) => $q->where('descripcion', 'like', "%{$search}%"))
            ->when($desde,  fn($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta,  fn($q) => $q->whereDate('fecha', '<=', $hasta))
            ->orderByDesc('fecha')
            ->paginate($perPage);
    }

    public function allByEmpresa(int $empresaId): Collection
    {
        return EgresoCompra::where('empresa_id', $empresaId)
            ->with(['usuario', 'compra'])
            ->orderByDesc('fecha')
            ->get();
    }

    public function allByCompra(int $compraId): Collection
    {
        return EgresoCompra::where('compra_id', $compraId)
            ->where('estado', 'ACTIVO')
            ->with('usuario')
            ->orderByDesc('fecha')
            ->get();
    }

    public function findById(int $id): ?EgresoCompra
    {
        return EgresoCompra::with(['usuario', 'compra'])->find($id);
    }

    public function registrar(array $data, int $empresaId, int $usuarioId): EgresoCompra
    {
        return DB::transaction(function () use ($data, $empresaId, $usuarioId) {
            $egreso = EgresoCompra::create([
                ...$data,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'estado'     => 'ACTIVO',
            ]);

            CajaMovimiento::create([
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'origen_tipo' => 'EGRESO_COMPRA',
                'origen_id'   => $egreso->id,
                'descripcion' => $egreso->descripcion,
                'monto'       => $egreso->monto,
                'fecha'       => $egreso->fecha,
                'created_at'  => now(),
            ]);

            return $egreso->fresh(['usuario', 'compra']);
        });
    }

    public function anular(int $id, int $empresaId): EgresoCompra
    {
        return DB::transaction(function () use ($id, $empresaId) {
            $egreso = EgresoCompra::where('empresa_id', $empresaId)->findOrFail($id);

            CajaMovimiento::where('origen_tipo', 'EGRESO_COMPRA')
                ->where('origen_id', $id)
                ->where('empresa_id', $empresaId)
                ->delete();

            $egreso->update(['estado' => 'ANULADO']);
            return $egreso->fresh();
        });
    }
}
