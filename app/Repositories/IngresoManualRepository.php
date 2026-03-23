<?php

namespace App\Repositories;

use App\Models\CajaMovimiento;
use App\Models\IngresoManual;
use App\Repositories\Contracts\IngresoManualRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IngresoManualRepository implements IngresoManualRepositoryInterface
{
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

            $ingreso = IngresoManual::create([
                ...$data,
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

    public function anular(int $id, int $empresaId): IngresoManual
    {
        return DB::transaction(function () use ($id, $empresaId) {

            $ingreso = IngresoManual::where('empresa_id', $empresaId)
                ->findOrFail($id);

            CajaMovimiento::where('origen_tipo', 'INGRESO_MANUAL')
                ->where('origen_id', $id)
                ->where('empresa_id', $empresaId)
                ->delete();

            $ingreso->update(['estado' => 'ANULADO']);

            return $ingreso->fresh();
        });
    }
}
