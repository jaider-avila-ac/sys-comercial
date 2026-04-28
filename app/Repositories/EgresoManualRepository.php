<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\EgresoManual;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EgresoManualRepository
{
    public function paginate(int $empresaId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        $desde  = $filters['desde'] ?? null;
        $hasta  = $filters['hasta'] ?? null;
        $estado = $filters['estado'] ?? null;

        return EgresoManual::where('empresa_id', $empresaId)
            ->with('usuario')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('descripcion', 'like', "%{$search}%")
                        ->orWhere('notas', 'like', "%{$search}%");
                });
            })
            ->when($desde, fn($q) => $q->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn($q) => $q->whereDate('fecha', '<=', $hasta))
            ->when($estado, fn($q) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->paginate($perPage);
    }

    public function allByEmpresa(int $empresaId): Collection
    {
        return EgresoManual::where('empresa_id', $empresaId)
            ->with('usuario')
            ->orderByDesc('fecha')
            ->get();
    }

    public function findById(int $id): ?EgresoManual
    {
        return EgresoManual::with('usuario')->find($id);
    }

    public function registrar(array $data, int $empresaId, int $usuarioId): EgresoManual
    {
        return DB::transaction(function () use ($data, $empresaId, $usuarioId) {
            if (isset($data['archivo']) && $data['archivo']) {
                $archivo = $data['archivo'];

                $data['archivo_nombre'] = $archivo->getClientOriginalName();
                $data['archivo_mime']   = $archivo->getClientMimeType();
                $data['archivo_path']   = $archivo->store('egresos_manuales', 'public');

                unset($data['archivo']);
            }

            $egreso = EgresoManual::create([
                ...$data,
                'fecha'      => now()->toDateString(),
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'estado'     => 'ACTIVO',
            ]);

            CajaMovimiento::create([
                'empresa_id'  => $empresaId,
                'usuario_id'  => $usuarioId,
                'origen_tipo' => 'EGRESO_MANUAL',
                'origen_id'   => $egreso->id,
                'descripcion' => $egreso->descripcion,
                'monto'       => $egreso->monto,
                'fecha'       => $egreso->fecha,
                'created_at'  => now(),
            ]);

            return $egreso->fresh('usuario');
        });
    }

    public function actualizar(int $id, array $data, int $empresaId): EgresoManual
    {
        return DB::transaction(function () use ($id, $data, $empresaId) {
            $egreso = EgresoManual::where('empresa_id', $empresaId)->findOrFail($id);

            if (isset($data['archivo']) && $data['archivo']) {
                if ($egreso->archivo_path) {
                    Storage::disk('public')->delete($egreso->archivo_path);
                }

                $archivo = $data['archivo'];

                $data['archivo_nombre'] = $archivo->getClientOriginalName();
                $data['archivo_mime']   = $archivo->getClientMimeType();
                $data['archivo_path']   = $archivo->store('egresos_manuales', 'public');

                unset($data['archivo']);
            }

            $egreso->update($data);

            CajaMovimiento::where('origen_tipo', 'EGRESO_MANUAL')
                ->where('origen_id', $egreso->id)
                ->where('empresa_id', $empresaId)
                ->update([
                    'descripcion' => $egreso->descripcion,
                    'monto'       => $egreso->monto,
                    'fecha'       => $egreso->fecha,
                ]);

            return $egreso->fresh('usuario');
        });
    }

    public function anular(int $id, int $empresaId): EgresoManual
    {
        return DB::transaction(function () use ($id, $empresaId) {
            $egreso = EgresoManual::where('empresa_id', $empresaId)->findOrFail($id);

            CajaMovimiento::where('origen_tipo', 'EGRESO_MANUAL')
                ->where('origen_id', $egreso->id)
                ->where('empresa_id', $empresaId)
                ->delete();

            $egreso->update([
                'estado' => 'ANULADO',
            ]);

            return $egreso->fresh('usuario');
        });
    }
}