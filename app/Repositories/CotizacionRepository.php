<?php

namespace App\Repositories;

use App\Models\Cotizacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CotizacionRepository
{
      public function paginar(int $empresaId, array $filters = [], int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $query = Cotizacion::where('empresa_id', $empresaId)
            ->with(['cliente'])
            ->orderByDesc('created_at');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('numero', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('cliente', fn($c) =>
                      $c->where('nombre_razon_social', 'like', '%' . $filters['search'] . '%')
                  );
            });
        }

        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function findById(int $id): ?Cotizacion
    {
        return Cotizacion::with(['cliente', 'lineas.item'])->find($id);
    }

    public function create(array $cabecera, array $lineas): Cotizacion
    {
        return DB::transaction(function () use ($cabecera, $lineas) {
            $cotizacion = Cotizacion::create($cabecera);
            $this->syncLineas($cotizacion, $lineas); // ✅ guardar líneas
            return $cotizacion->fresh(['cliente', 'lineas.item']);
        });
    }

    public function update(int $id, array $cabecera, array $lineas): Cotizacion
    {
        return DB::transaction(function () use ($id, $cabecera, $lineas) {
            $cotizacion = Cotizacion::findOrFail($id);
            $cotizacion->update($cabecera);

            if (!empty($lineas)) {
                $this->syncLineas($cotizacion, $lineas); // ✅ reemplazar líneas
            }

            return $cotizacion->fresh(['cliente', 'lineas.item']);
        });
    }

    public function cambiarEstado(int $id, string $estado): Cotizacion
    {
        $cotizacion = Cotizacion::findOrFail($id);
        $cotizacion->update(['estado' => $estado]);
        return $cotizacion->fresh(['cliente', 'lineas.item']);
    }

    public function delete(int $id): void
    {
        Cotizacion::findOrFail($id)->delete();
    }

    // ── Privado ───────────────────────────────────────────────────────────────
    private function syncLineas(Cotizacion $cotizacion, array $lineas): void
    {
        $cotizacion->lineas()->delete(); // borrar las anteriores

        foreach ($lineas as $linea) {
            $cotizacion->lineas()->create([
                'item_id'            => $linea['item_id']            ?? null,
                'descripcion_manual' => $linea['descripcion_manual'] ?? null,
                'cantidad'           => $linea['cantidad'],
                'valor_unitario'     => $linea['valor_unitario'],
                'descuento'          => $linea['descuento']          ?? 0,
                'iva_pct'            => $linea['iva_pct']            ?? 0,
                'iva_valor'          => $linea['iva_valor']          ?? 0,
                'total_linea'        => $linea['total_linea']        ?? 0,
            ]);
        }
    }
}