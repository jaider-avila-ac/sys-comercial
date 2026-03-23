<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\EgresoManual;
use App\Repositories\Contracts\EgresoManualRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EgresoManualRepository implements EgresoManualRepositoryInterface
{
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

            $egreso = EgresoManual::create([
                ...$data,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'estado'     => 'ACTIVO',
            ]);

            // Registrar en caja como egreso
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

    public function anular(int $id, int $empresaId): EgresoManual
    {
        return DB::transaction(function () use ($id, $empresaId) {

            $egreso = EgresoManual::where('empresa_id', $empresaId)
                ->findOrFail($id);

            // Eliminar movimiento de caja
            CajaMovimiento::where('origen_tipo', 'EGRESO_MANUAL')
                ->where('origen_id', $id)
                ->where('empresa_id', $empresaId)
                ->delete();

            $egreso->update(['estado' => 'ANULADO']);

            return $egreso->fresh();
        });
    }
}
