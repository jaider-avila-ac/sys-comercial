<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\IngresoManual;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IngresoManualRepository
{
    public function paginate(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        $desde  = $filters['desde'] ?? null;
        $hasta  = $filters['hasta'] ?? null;
        $estado = $filters['estado'] ?? null;

        return IngresoManual::where('empresa_id', $empresaId)
            ->with('usuario')
            ->when($search, fn ($q) => $q->where('descripcion', 'like', "%{$search}%"))
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->paginate($perPage);
    }

    public function allByEmpresa(int $empresaId): Collection
    {
        return IngresoManual::where('empresa_id', $empresaId)
            ->with('usuario')
            ->orderByDesc('fecha')
            ->get();
    }

    public function findById(int $id): ?IngresoManual
    {
        return IngresoManual::with('usuario')->find($id);
    }

    public function registrar(array $data, int $empresaId, int $usuarioId): IngresoManual
    {
        return DB::transaction(function () use ($data, $empresaId, $usuarioId) {
            if (isset($data['archivo']) && $data['archivo']) {
                $archivo = $data['archivo'];

                $data['archivo_nombre'] = $archivo->getClientOriginalName();
                $data['archivo_mime']   = $archivo->getClientMimeType();
                $data['archivo_path']   = $archivo->store('ingresos_manuales', 'public');

                unset($data['archivo']);
            }

            $ingreso = IngresoManual::create([
                ...$data,
                'fecha'      => now()->toDateString(),
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'estado'     => 'ACTIVO',
            ]);

            CajaMovimiento::create([
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'origen_tipo' => 'INGRESO_MANUAL',
                'origen_id'   => $ingreso->id,
                'descripcion' => $ingreso->descripcion,
                'monto'       => $ingreso->monto,
                'fecha'       => $ingreso->fecha,
                'created_at'  => now(),
            ]);

            return $ingreso->fresh('usuario');
        });
    }

    public function actualizar(int $id, array $data, int $empresaId): IngresoManual
    {
        return DB::transaction(function () use ($id, $data, $empresaId) {
            $ingreso = IngresoManual::where('empresa_id', $empresaId)->findOrFail($id);

            if (isset($data['archivo']) && $data['archivo']) {
                if ($ingreso->archivo_path) {
                    Storage::disk('public')->delete($ingreso->archivo_path);
                }

                $archivo = $data['archivo'];

                $data['archivo_nombre'] = $archivo->getClientOriginalName();
                $data['archivo_mime']   = $archivo->getClientMimeType();
                $data['archivo_path']   = $archivo->store('ingresos_manuales', 'public');

                unset($data['archivo']);
            }

            $ingreso->update($data);

            CajaMovimiento::where('origen_tipo', 'INGRESO_MANUAL')
                ->where('origen_id', $ingreso->id)
                ->where('empresa_id', $empresaId)
                ->update([
                    'descripcion' => $ingreso->descripcion,
                    'monto'       => $ingreso->monto,
                    'fecha'       => $ingreso->fecha,
                ]);

            return $ingreso->fresh('usuario');
        });
    }

    public function anular(int $id, int $empresaId): IngresoManual
    {
        return DB::transaction(function () use ($id, $empresaId) {
            $ingreso = IngresoManual::where('empresa_id', $empresaId)->findOrFail($id);

            CajaMovimiento::where('origen_tipo', 'INGRESO_MANUAL')
                ->where('origen_id', $ingreso->id)
                ->where('empresa_id', $empresaId)
                ->delete();

            $ingreso->update([
                'estado' => 'ANULADO',
            ]);

            return $ingreso->fresh('usuario');
        });
    }
}