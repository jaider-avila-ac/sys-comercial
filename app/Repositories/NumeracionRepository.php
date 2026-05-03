<?php

namespace App\Repositories;

use App\Models\Numeracion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NumeracionRepository
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

  public function crearParaEmpresa(int $empresaId): void
{
    $registros = array_map(fn($tipo) => [
        'empresa_id'  => $empresaId,
        'tipo'        => $tipo,
        'prefijo'     => Numeracion::PREFIJOS_DEFAULT[$tipo],
        'consecutivo' => 0,
        'relleno'     => 5,
    ], Numeracion::TIPOS);

    Numeracion::insert($registros);
}

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

            $numero = str_pad((string) $siguiente, $numeracion->relleno, '0', STR_PAD_LEFT);

            return "{$numeracion->prefijo}-{$numero}";
        });
    }

    public function update(int $empresaId, string $tipo, array $data): Numeracion
    {
        $numeracion = Numeracion::where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->firstOrFail();

        $numeracion->update([
            ...$data,
            'updated_at' => now(),
        ]);

        return $numeracion->fresh();
    }
}