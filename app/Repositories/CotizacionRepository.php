<?php

namespace App\Repositories;

use App\Models\Cotizacion;
use App\Models\CotizacionLinea;
use App\Repositories\Contracts\CotizacionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CotizacionRepository implements CotizacionRepositoryInterface
{
    public function allByEmpresa(int $empresaId): Collection
    {
        return Cotizacion::where('empresa_id', $empresaId)
            ->with(['cliente', 'usuario'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id): ?Cotizacion
    {
        return Cotizacion::with(['lineas.item', 'cliente', 'usuario'])->find($id);
    }

    public function create(array $cabecera, array $lineas): Cotizacion
    {
        return DB::transaction(function () use ($cabecera, $lineas) {
            $cotizacion = Cotizacion::create($cabecera);
            $this->sincronizarLineas($cotizacion->id, $lineas);
            return $cotizacion->fresh(['lineas.item', 'cliente', 'usuario']);
        });
    }

    public function update(int $id, array $cabecera, array $lineas): Cotizacion
    {
        return DB::transaction(function () use ($id, $cabecera, $lineas) {
            $cotizacion = Cotizacion::findOrFail($id);
            $cotizacion->update($cabecera);
            $this->sincronizarLineas($id, $lineas);
            return $cotizacion->fresh(['lineas.item', 'cliente', 'usuario']);
        });
    }

    public function cambiarEstado(int $id, string $estado): Cotizacion
    {
        $cotizacion = Cotizacion::findOrFail($id);
        $cotizacion->update(['estado' => $estado]);
        return $cotizacion->fresh();
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            CotizacionLinea::where('cotizacion_id', $id)->delete();
            Cotizacion::findOrFail($id)->delete();
        });
    }

    // ── Privado ───────────────────────────────────────────────────────────────

    private function sincronizarLineas(int $cotizacionId, array $lineas): void
    {
        // Eliminar líneas anteriores y reemplazar — más simple y seguro
        CotizacionLinea::where('cotizacion_id', $cotizacionId)->delete();

        $registros = array_map(fn($l) => [
            ...$l,
            'cotizacion_id' => $cotizacionId,
        ], $lineas);

        CotizacionLinea::insert($registros);
    }
}