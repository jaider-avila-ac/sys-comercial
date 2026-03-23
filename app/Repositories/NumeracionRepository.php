<?php

namespace App\Repositories;

use App\Models\Numeracion;
use App\Repositories\Contracts\NumeracionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NumeracionRepository implements NumeracionRepositoryInterface
{
    public function findByEmpresaYTipo(int $empresaId, string $tipo): ?Numeracion
    {
        return Numeracion::where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->first();
    }

    public function allByEmpresa(int $empresaId): Collection
    {
        return Numeracion::where('empresa_id', $empresaId)
            ->orderByRaw("FIELD(tipo, 'COT','FAC','REC','COM','MOS','EGR')")
            ->get();
    }

    /**
     * Crea los 6 registros de numeración para una empresa nueva.
     * Cada empresa empieza desde consecutivo = 0, independiente de las demás.
     */
    public function crearParaEmpresa(int $empresaId): void
    {
        $ahora = now();

        $registros = array_map(fn($tipo) => [
            'empresa_id'   => $empresaId,
            'tipo'         => $tipo,
            'prefijo'      => Numeracion::PREFIJOS_DEFAULT[$tipo],
            'consecutivo'  => 0,
            'relleno'      => 5,
            'updated_at'   => $ahora,
        ], Numeracion::TIPOS);

        Numeracion::insert($registros);
    }

    /**
     * Incrementa el consecutivo de forma atómica y retorna el número formateado.
     * Usa DB::transaction + lockForUpdate para evitar duplicados en concurrencia.
     */
    public function incrementar(int $empresaId, string $tipo): string
    {
        return DB::transaction(function () use ($empresaId, $tipo) {
            $numeracion = Numeracion::where('empresa_id', $empresaId)
                ->where('tipo', $tipo)
                ->lockForUpdate()
                ->firstOrFail();

            $siguiente = $numeracion->consecutivo + 1;

            $numeracion->update([
                'consecutivo' => $siguiente,
                'updated_at'  => now(),
            ]);

            // Formatear: prefijo + consecutivo con relleno de ceros
            $numero = str_pad($siguiente, $numeracion->relleno, '0', STR_PAD_LEFT);

            return "{$numeracion->prefijo}-{$numero}";
        });
    }

    public function update(int $empresaId, string $tipo, array $data): Numeracion
    {
        $numeracion = Numeracion::where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->firstOrFail();

        $numeracion->update([...$data, 'updated_at' => now()]);

        return $numeracion->fresh();
    }
}